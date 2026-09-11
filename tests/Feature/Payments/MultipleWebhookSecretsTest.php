<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Services\Payment\PaymentWebhookSignatureService;
use App\Services\Payment\WebhookSecretState;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A gateway account can register several webhook endpoints, each
 * issued its own secret. This platform registers three Razorpay
 * endpoints — booking payments, package purchases and wallet recharges
 * — so verifying against a single stored secret would reject every
 * delivery from the other endpoints as a forgery (11 Sep 2026: six
 * booking deliveries answered 401 while the shared secret was right
 * for a different endpoint).
 */
class MultipleWebhookSecretsTest extends TestCase
{
    use RefreshDatabase;

    private function settingsWithSecrets(string $value): PaymentGatewaySettings
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_webhook_secret = Crypt::encryptString($value);

        return $settings;
    }

    private function razorpayRequest(string $payload, string $secret): Request
    {
        $request = Request::create('/api/webhooks/packages/purchases/razorpay', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Razorpay-Signature', hash_hmac('sha256', $payload, $secret));

        return $request;
    }

    public function test_a_single_configured_secret_still_verifies(): void
    {
        $settings = $this->settingsWithSecrets('only_secret');

        $this->assertTrue(app(PaymentWebhookSignatureService::class)->isValid(
            'razorpay',
            $this->razorpayRequest('{"event":"payment.captured"}', 'only_secret'),
            $settings,
        ));
    }

    public function test_either_of_two_configured_secrets_verifies(): void
    {
        $settings = $this->settingsWithSecrets("booking_secret\npackage_secret");
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        // The booking endpoint's secret.
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings));

        // The package endpoint's secret — this is the delivery that was
        // previously rejected as forged.
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings));
    }

    public function test_blank_lines_and_whitespace_are_ignored(): void
    {
        $settings = $this->settingsWithSecrets("  booking_secret  \n\n\n  package_secret\n");
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings));
    }

    /** Accepting several secrets must not weaken the actual check. */
    public function test_an_unknown_secret_is_still_rejected(): void
    {
        $settings = $this->settingsWithSecrets("booking_secret\npackage_secret");

        $this->assertFalse(app(PaymentWebhookSignatureService::class)->isValid(
            'razorpay',
            $this->razorpayRequest('{"event":"payment.captured"}', 'attacker_secret'),
            $settings,
        ));
    }

    public function test_a_tampered_payload_is_still_rejected(): void
    {
        $settings = $this->settingsWithSecrets("booking_secret\npackage_secret");

        $request = $this->razorpayRequest('{"event":"payment.captured"}', 'booking_secret');
        $tampered = Request::create('/api/webhooks/packages/purchases/razorpay', 'POST', [], [], [], [], '{"event":"payment.captured","amount":1}');
        $tampered->headers->set('X-Razorpay-Signature', (string) $request->header('X-Razorpay-Signature'));

        $this->assertFalse(app(PaymentWebhookSignatureService::class)->isValid('razorpay', $tampered, $settings));
    }

    /** A gateway with real verification must still fail closed when unconfigured. */
    // ── Endpoint isolation ────────────────────────────────────────────

    public function test_a_booking_scoped_secret_cannot_authenticate_the_package_endpoint(): void
    {
        $settings = $this->settingsWithSecrets("booking:booking_secret\npackage:package_secret");
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        // Each endpoint accepts its own secret...
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));

        // ...and rejects the other's. A leak of one endpoint's secret
        // must never become authority over the other.
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    /** Rotation: two secrets for the SAME endpoint are both live. */
    public function test_rotating_a_booking_secret_keeps_both_live_without_leaking_to_package(): void
    {
        $settings = $this->settingsWithSecrets("booking:old_booking\nbooking:new_booking\npackage:package_secret");
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'old_booking'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'new_booking'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));

        // Neither rotation secret reaches the package endpoint.
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'old_booking'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'new_booking'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
    }

    /** Back-compat: an existing unprefixed secret keeps working everywhere. */
    public function test_an_unprefixed_legacy_secret_still_authenticates_every_endpoint(): void
    {
        $settings = $this->settingsWithSecrets('legacy_secret');
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'legacy_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'legacy_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
    }

    /** A secret containing a colon must not be truncated by prefix parsing. */
    public function test_a_secret_containing_a_colon_is_not_mistaken_for_a_scope(): void
    {
        $settings = $this->settingsWithSecrets('whsec:with:colons');

        $this->assertTrue(app(PaymentWebhookSignatureService::class)->isValid(
            'razorpay',
            $this->razorpayRequest('{"event":"payment.captured"}', 'whsec:with:colons'),
            $settings,
            PaymentWebhookSignatureService::PURPOSE_BOOKING,
        ));
    }

    public function test_no_configured_secret_still_fails_closed_for_razorpay(): void
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_webhook_secret = null;

        $this->assertFalse(app(PaymentWebhookSignatureService::class)->isValid(
            'razorpay',
            $this->razorpayRequest('{"event":"payment.captured"}', 'anything'),
            $settings,
        ));
    }

    // ── Dedicated per-endpoint fields ─────────────────────────────────

    /** @param  array<string, string>  $dedicated  purpose => secret lines */
    private function settingsWithDedicated(array $dedicated, ?string $legacy = null): PaymentGatewaySettings
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_webhook_secret = $legacy === null ? null : Crypt::encryptString($legacy);
        $settings->razorpay_booking_webhook_secret = null;
        $settings->razorpay_package_webhook_secret = null;
        $settings->razorpay_wallet_webhook_secret = null;

        foreach ($dedicated as $purpose => $value) {
            $settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)} = Crypt::encryptString($value);
        }

        return $settings;
    }

    public function test_each_dedicated_secret_authenticates_only_its_own_endpoint(): void
    {
        $settings = $this->settingsWithDedicated([
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'package_secret',
            PaymentWebhookSignatureService::PURPOSE_WALLET => 'wallet_secret',
        ]);
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        foreach ([
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'package_secret',
            PaymentWebhookSignatureService::PURPOSE_WALLET => 'wallet_secret',
        ] as $purpose => $own) {
            $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, $own), $settings, $purpose), "{$purpose} accepts its own secret");

            foreach (['booking_secret', 'package_secret', 'wallet_secret'] as $other) {
                if ($other === $own) {
                    continue;
                }

                $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, $other), $settings, $purpose), "{$purpose} rejects {$other}");
            }
        }
    }

    /** A dedicated field takes over completely: the legacy shared line stops being valid for that endpoint. */
    public function test_a_dedicated_secret_supersedes_the_legacy_shared_secret_for_its_endpoint(): void
    {
        $settings = $this->settingsWithDedicated([PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret'], legacy: 'shared_secret');
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'shared_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));

        // Endpoints WITHOUT a dedicated field still fall back to the shared line.
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'shared_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_WALLET));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'booking_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_WALLET));
    }

    public function test_a_dedicated_field_supports_rotation_with_two_lines(): void
    {
        $settings = $this->settingsWithDedicated([PaymentWebhookSignatureService::PURPOSE_PACKAGE => "old_package\nnew_package"]);
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'old_package'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'new_package'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'old_package'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    /** Legacy scoped lines keep working for one release, and a dedicated field for one endpoint does not disturb them. */
    public function test_legacy_scoped_lines_remain_compatible_alongside_dedicated_fields(): void
    {
        $settings = $this->settingsWithDedicated([PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret'], legacy: "package:package_secret\nwallet:wallet_secret");
        $service = app(PaymentWebhookSignatureService::class);
        $payload = '{"event":"payment.captured"}';

        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertTrue($service->isValid('razorpay', $this->razorpayRequest($payload, 'wallet_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_WALLET));
        $this->assertFalse($service->isValid('razorpay', $this->razorpayRequest($payload, 'package_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    /** Operators see how each endpoint is configured — and a shared legacy secret is never reported as "configured". */
    public function test_secret_state_reports_dedicated_legacy_and_missing_per_endpoint(): void
    {
        $settings = $this->settingsWithDedicated([PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret'], legacy: 'package:package_secret');

        $this->assertSame(WebhookSecretState::Configured, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertSame(WebhookSecretState::LegacyScoped, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_WALLET));

        $shared = $this->settingsWithDedicated([], legacy: 'one_shared_secret');

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $state = PaymentWebhookSignatureService::secretState($shared, 'razorpay', $purpose);
            $this->assertSame(WebhookSecretState::LegacyUnscoped, $state);
            $this->assertTrue($state->isVerifiable());
            $this->assertTrue($state->isLegacy());
        }
    }

    // ── Through the real HTTP endpoints ───────────────────────────────

    /**
     * The production shape: three Razorpay endpoints, three different
     * secrets. A delivery signed with the wrong endpoint's secret must be
     * refused with 401 by the controller, and a correctly signed one
     * must reach parsing (200, even when the payment is unknown).
     */
    public function test_the_three_endpoints_only_accept_their_own_secret_over_http(): void
    {
        $settings = $this->settingsWithDedicated([
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'package_secret',
            PaymentWebhookSignatureService::PURPOSE_WALLET => 'wallet_secret',
        ]);
        $settings->razorpay_enabled = true;
        $settings->save();

        $body = (string) json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_unknown', 'order_id' => 'order_unknown', 'amount' => 100, 'currency' => 'INR', 'notes' => ['payment_reference' => 'PAY-UNKNOWN'],
        ]]]]);

        $endpoints = [
            'booking_secret' => '/api/webhooks/bookings/payments/razorpay',
            'package_secret' => '/api/webhooks/packages/purchases/razorpay',
            'wallet_secret' => '/api/webhooks/wallets/recharges/razorpay',
        ];

        foreach ($endpoints as $own => $path) {
            $this->postSigned($path, $body, $own)->assertOk();

            foreach (array_keys($endpoints) as $other) {
                if ($other !== $own) {
                    $this->postSigned($path, $body, $other)->assertStatus(401);
                }
            }
        }
    }

    private function postSigned(string $path, string $body, string $secret): TestResponse
    {
        return $this->call('POST', $path, [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }
}
