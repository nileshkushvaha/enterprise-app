<?php

declare(strict_types=1);

use App\Services\Payment\PaymentWebhookSignatureService;
use Illuminate\Support\Facades\Crypt;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Razorpay issues a distinct secret for every webhook endpoint, and
 * this platform registers three (booking payments, package purchases,
 * wallet recharges). This migration creates one field per endpoint and
 * copies any `booking:` / `package:` / `wallet:` prefixed line from the
 * old shared field into the field for its endpoint (several
 * same-endpoint lines stay together — that is rotation). An unprefixed
 * line cannot be attributed to an endpoint and is not copied.
 *
 * The old field is removed by the following migration
 * (2026_09_11_100100_remove_legacy_razorpay_webhook_secret), once the
 * three dedicated fields exist.
 */
return new class extends SettingsMigration
{
    private const string LEGACY = 'payment_gateways.razorpay_webhook_secret';

    public function up(): void
    {
        $legacyValue = null;

        if ($this->migrator->exists(self::LEGACY)) {
            // update() is the only read primitive the migrator offers;
            // the closure hands the stored value back unchanged.
            $this->migrator->update(self::LEGACY, function (mixed $stored) use (&$legacyValue): mixed {
                $legacyValue = $stored;

                return $stored;
            });
        }

        $lines = PaymentWebhookSignatureService::lines($this->decrypt($legacyValue));

        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $property = 'payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose);

            if ($this->migrator->exists($property)) {
                continue;
            }

            $scoped = collect($lines)
                ->where('scope', $purpose)
                ->pluck('secret')
                ->unique()
                ->values();

            $this->migrator->add(
                $property,
                $scoped->isEmpty() ? null : Crypt::encryptString($scoped->implode("\n")),
            );
        }
    }

    public function down(): void
    {
        foreach (PaymentWebhookSignatureService::PURPOSES as $purpose) {
            $this->migrator->deleteIfExists('payment_gateways.'.PaymentWebhookSignatureService::dedicatedField('razorpay', $purpose));
        }
    }

    /** Stored values are app-level Crypt ciphertext; very old installs may hold plain text. */
    private function decrypt(mixed $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return str_starts_with($stored, 'eyJpdiI6') ? null : $stored;
        }
    }
};
