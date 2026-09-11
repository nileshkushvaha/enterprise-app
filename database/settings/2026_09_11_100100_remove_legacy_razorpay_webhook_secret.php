<?php

declare(strict_types=1);

use App\Services\Payment\PaymentWebhookSignatureService;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Removes the old shared Razorpay webhook secret field. The three
 * per-endpoint fields (`razorpay_booking_webhook_secret`,
 * `razorpay_package_webhook_secret`, `razorpay_wallet_webhook_secret`)
 * are the only supported configuration; the previous migration created
 * them and copied any endpoint-prefixed lines across.
 *
 * Runs only once all three dedicated fields exist, so a database that
 * skipped the previous migration is never left without any Razorpay
 * webhook secret field at all. An unprefixed shared value that was
 * still in the old field cannot be attributed to an endpoint and is
 * dropped; the operator pastes each endpoint's secret from the Razorpay
 * dashboard.
 */
return new class extends SettingsMigration
{
    private const string LEGACY = 'payment_gateways.razorpay_webhook_secret';

    public function up(): void
    {
        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            if (! $this->migrator->exists('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose))) {
                return;
            }
        }

        $this->migrator->deleteIfExists(self::LEGACY);
    }

    public function down(): void
    {
        if (! $this->migrator->exists(self::LEGACY)) {
            $this->migrator->add(self::LEGACY, null);
        }
    }
};
