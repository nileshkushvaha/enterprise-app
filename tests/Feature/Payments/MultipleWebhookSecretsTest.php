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
 * Razorpay issues a distinct secret for every webhook endpoint, and this
 * platform registers three — booking payments, package purchases and
 * wallet recharges. Each has its own settings field and only that field
 * can authenticate deliveries to it (11 Sep 2026: six booking
 * deliveries answered 401 while one shared value was right for a
 * different endpoint).
 */
class MultipleWebhookSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '{"event":"payment.captured"}';

    /** @param  array<string, string>  $secrets  purpose => secret lines */
    private function razorpaySettings(array $secrets): PaymentGatewaySettings
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->razorpay_booking_webhook_secret = null;
        $settings->razorpay_package_webhook_secret = null;
        $settings->razorpay_wallet_webhook_secret = null;

        foreach ($secrets as $purpose => $value) {
            $settings->{PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose)} = Crypt::encryptString($value);
        }

        return $settings;
    }

    private function signed(string $payload, string $secret): Request
    {
        $request = Request::create('/api/webhooks/bookings/payments/razorpay', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Razorpay-Signature', hash_hmac('sha256', $payload, $secret));

        return $request;
    }

    private function service(): PaymentWebhookSignatureService
    {
        return app(PaymentWebhookSignatureService::class);
    }

    // ── One secret per endpoint ───────────────────────────────────────

    public function test_each_secret_authenticates_only_its_own_endpoint(): void
    {
        $own = [
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'package_secret',
            PaymentWebhookSignatureService::PURPOSE_WALLET => 'wallet_secret',
        ];
        $settings = $this->razorpaySettings($own);

        foreach ($own as $purpose => $secret) {
            $this->assertTrue($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, $secret), $settings, $purpose), "{$purpose} accepts its own secret");

            foreach ($own as $otherPurpose => $other) {
                if ($otherPurpose !== $purpose) {
                    $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, $other), $settings, $purpose), "{$purpose} rejects the {$otherPurpose} secret");
                }
            }
        }
    }

    public function test_a_missing_endpoint_secret_fails_closed_even_when_the_others_are_configured(): void
    {
        $settings = $this->razorpaySettings([
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'package_secret',
        ]);

        foreach (['booking_secret', 'package_secret', 'anything'] as $secret) {
            $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, $secret), $settings, PaymentWebhookSignatureService::PURPOSE_WALLET));
        }

        $this->assertSame(WebhookSecretState::Missing, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_WALLET));
        $this->assertSame(WebhookSecretState::Configured, PaymentWebhookSignatureService::secretState($settings, 'razorpay', PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    public function test_no_configured_secret_fails_closed(): void
    {
        $settings = $this->razorpaySettings([]);

        $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'anything'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'anything'), $settings));
    }

    /** Rotation: the field holds the old and the new secret on two lines, both live; still only for its own endpoint. */
    public function test_a_field_holds_two_secrets_during_rotation(): void
    {
        $settings = $this->razorpaySettings([PaymentWebhookSignatureService::PURPOSE_PACKAGE => "old_package\n  new_package  \n"]);

        $this->assertTrue($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'old_package'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertTrue($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'new_package'), $settings, PaymentWebhookSignatureService::PURPOSE_PACKAGE));
        $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'old_package'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    public function test_an_unknown_secret_and_a_tampered_payload_are_rejected(): void
    {
        $settings = $this->razorpaySettings([PaymentWebhookSignatureService::PURPOSE_BOOKING => 'booking_secret']);

        $this->assertFalse($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'attacker_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));

        $valid = $this->signed(self::PAYLOAD, 'booking_secret');
        $tampered = Request::create('/api/webhooks/bookings/payments/razorpay', 'POST', [], [], [], [], '{"event":"payment.captured","amount":1}');
        $tampered->headers->set('X-Razorpay-Signature', (string) $valid->header('X-Razorpay-Signature'));

        $this->assertFalse($this->service()->isValid('razorpay', $tampered, $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    /** A secret that happens to contain a colon is a secret, not a scope. */
    public function test_a_secret_containing_a_colon_is_used_verbatim(): void
    {
        $settings = $this->razorpaySettings([PaymentWebhookSignatureService::PURPOSE_BOOKING => 'whsec:with:colons']);

        $this->assertTrue($this->service()->isValid('razorpay', $this->signed(self::PAYLOAD, 'whsec:with:colons'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
    }

    // ── Stripe keeps one field with optional endpoint prefixes ────────

    public function test_a_stripe_line_prefixed_for_one_endpoint_does_not_authenticate_another(): void
    {
        $settings = app(PaymentGatewaySettings::class);
        $settings->stripe_webhook_secret = Crypt::encryptString("wallet:wallet_secret\nshared_secret");

        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = (string) time();
        $signed = function (string $secret) use ($payload, $timestamp): Request {
            $request = Request::create('/api/webhooks/wallets/recharges/stripe', 'POST', [], [], [], [], $payload);
            $request->headers->set('Stripe-Signature', "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret));

            return $request;
        };

        $this->assertTrue($this->service()->isValid('stripe', $signed('wallet_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_WALLET));
        $this->assertFalse($this->service()->isValid('stripe', $signed('wallet_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
        $this->assertTrue($this->service()->isValid('stripe', $signed('shared_secret'), $settings, PaymentWebhookSignatureService::PURPOSE_BOOKING));
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
        $settings = $this->razorpaySettings([
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
