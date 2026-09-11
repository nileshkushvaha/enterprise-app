<?php

declare(strict_types=1);

use App\Services\Payment\PaymentWebhookSignatureService;
use Illuminate\Support\Facades\Crypt;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Razorpay issues a distinct secret for every webhook endpoint, and
 * this platform registers three (booking payments, package purchases,
 * wallet recharges). Until now all three lived in ONE multi-line field
 * with a `booking:` / `package:` / `wallet:` prefix convention. That
 * worked cryptographically but an operator who pasted the single
 * secret they were shown, unprefixed, produced an install that looked
 * "ready" while two of the three endpoints were rejected with 401
 * (the 11 Sep 2026 booking-webhook incident).
 *
 * This migration is ADDITIVE:
 *
 *  - three purpose-specific fields are created;
 *  - legacy PREFIXED lines are copied into the field for their purpose
 *    (several same-purpose lines stay together — that is rotation);
 *  - UNPREFIXED legacy lines are left exactly where they are. They are
 *    still honoured as a fallback for one release and are reported as
 *    "legacy fallback", never as three configured endpoints — one
 *    shared value cannot be right for three independent endpoints, so
 *    copying it three times would manufacture a readiness that is
 *    not real.
 *
 * The legacy field itself is untouched, so rolling back loses nothing.
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
