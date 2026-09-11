<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\DTOs\PaymentGatewayReadiness;
use App\Booking\Payments\FakePaymentProvider;
use App\Services\Payment\PaymentWebhookSignatureService;
use App\Services\Payment\WebhookSecretState;
use App\Settings\FeatureSettings;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Support\Carbon;

/**
 * Determines and persists each provider's admin-facing readiness
 * status (`not_configured`/`incomplete`/`invalid`/`ready`). Never
 * calls the gateway — this is pure settings/format inspection, the
 * same rule PaymentProviderConfigValidator follows, so this service's
 * "Validate Configuration" action is safe to run in automated tests
 * and does not require network access or stubbed HTTP responses.
 *
 * Distinct from PaymentProviderResolver: the resolver makes a
 * real-time, unpersisted decision at the moment a payment is
 * initiated (isConfigured() is re-evaluated every call, never cached);
 * this service is the admin-facing "click Validate Configuration and
 * see a status badge" bookkeeping layer, persisted so the admin UI can
 * render a status without re-deriving it on every page load.
 */
final class PaymentGatewayConfigurationService
{
    public function __construct(
        private readonly PaymentGatewaySettings $settings,
        private readonly PaymentProviderConfigValidator $validator,
        private readonly FeatureSettings $features,
    ) {}

    public function checkFake(): PaymentGatewayReadiness
    {
        // Never "ready" in the same sense as a real gateway — it moves no
        // money — but always structurally valid; PaymentProviderResolver
        // is what actually blocks it outside local/testing, not this status.
        return new PaymentGatewayReadiness(FakePaymentProvider::KEY, 'ready');
    }

    public function checkRazorpay(): PaymentGatewayReadiness
    {
        $issues = [];

        if (! $this->settings->razorpay_enabled) {
            return $this->persist('razorpay', 'not_configured', ['Razorpay is not enabled.']);
        }

        if (blank($this->settings->razorpay_key_id) || blank($this->settings->razorpay_key_secret)) {
            $issues[] = 'Razorpay Key ID or Key Secret is missing.';
        } elseif (! $this->validator->isValidRazorpayKeyId($this->settings->razorpay_key_id)) {
            return $this->persist('razorpay', 'invalid', ['Razorpay Key ID must start with rzp_test_ or rzp_live_.']);
        }

        // One secret per Razorpay webhook endpoint. An endpoint whose
        // domain is switched off (wallet, packages) is not required, so a
        // platform that only sells lessons is not held "incomplete" by a
        // webhook it never receives.
        foreach (self::requiredRazorpayWebhookPurposes($this->features) as $purpose) {
            if (PaymentWebhookSignatureService::secretState($this->settings, 'razorpay', $purpose) === WebhookSecretState::Missing) {
                $issues[] = sprintf('%s webhook secret is missing.', self::purposeLabel($purpose));
            }
        }

        if ($issues !== []) {
            return $this->persist('razorpay', 'incomplete', $issues);
        }

        return $this->persist('razorpay', 'ready');
    }

    /**
     * The Razorpay webhook endpoints that must be verifiable for the
     * features currently enabled. Booking payments are always live;
     * wallet recharges and package purchases follow their feature flags.
     *
     * @return list<string>
     */
    public static function requiredRazorpayWebhookPurposes(FeatureSettings $features): array
    {
        $purposes = [PaymentWebhookSignatureService::PURPOSE_BOOKING];

        if ($features->country_academic_packages_enabled) {
            $purposes[] = PaymentWebhookSignatureService::PURPOSE_PACKAGE;
        }

        if ($features->wallet_enabled) {
            $purposes[] = PaymentWebhookSignatureService::PURPOSE_WALLET;
        }

        return $purposes;
    }

    /** Administrator-facing name of one webhook endpoint. */
    public static function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            PaymentWebhookSignatureService::PURPOSE_BOOKING => 'Booking payment',
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => 'Package purchase',
            PaymentWebhookSignatureService::PURPOSE_WALLET => 'Wallet recharge',
            default => ucfirst($purpose),
        };
    }

    /**
     * The webhook endpoints a gateway is registered for, keyed by the
     * secret purpose that verifies them.
     *
     * @return array<string, string>
     */
    public static function webhookEndpoints(string $gateway): array
    {
        return [
            PaymentWebhookSignatureService::PURPOSE_BOOKING => "/api/webhooks/bookings/payments/{$gateway}",
            PaymentWebhookSignatureService::PURPOSE_PACKAGE => "/api/webhooks/packages/purchases/{$gateway}",
            PaymentWebhookSignatureService::PURPOSE_WALLET => "/api/webhooks/wallets/recharges/{$gateway}",
        ];
    }

    public function checkStripe(): PaymentGatewayReadiness
    {
        $issues = [];

        if (! $this->settings->stripe_enabled) {
            return $this->persist('stripe', 'not_configured', ['Stripe is not enabled.']);
        }

        $secretKey = PaymentWebhookSignatureService::decryptSecret($this->settings, 'stripe_secret_key');

        if (blank($secretKey) || blank($this->settings->stripe_publishable_key)) {
            $issues[] = 'Stripe secret_key or publishable_key is missing.';
        } else {
            if (! $this->validator->isValidStripeSecretKey($secretKey)) {
                return $this->persist('stripe', 'invalid', ['Stripe secret_key does not match the expected sk_(test|live)_... format.']);
            }

            if (! $this->validator->isValidStripePublishableKey($this->settings->stripe_publishable_key)) {
                return $this->persist('stripe', 'invalid', ['Stripe publishable_key does not match the expected pk_(test|live)_... format.']);
            }
        }

        if (blank($this->settings->stripe_webhook_secret)) {
            $issues[] = 'Stripe webhook secret is missing.';
        }

        if ($issues !== []) {
            return $this->persist('stripe', 'incomplete', $issues);
        }

        return $this->persist('stripe', 'ready');
    }

    /** @param  list<string>  $issues */
    private function persist(string $provider, string $status, array $issues = []): PaymentGatewayReadiness
    {
        $statusField = "{$provider}_config_status";
        $checkedField = "{$provider}_last_checked_at";

        $this->settings->{$statusField} = $status;
        $this->settings->{$checkedField} = Carbon::now()->toIso8601String();
        $this->settings->save();

        return new PaymentGatewayReadiness($provider, $status, $issues);
    }
}
