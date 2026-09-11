<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * How one webhook endpoint's secret is configured. Reported to
 * operators (settings page, readiness check, platform:audit-config)
 * without ever revealing the secret itself.
 */
enum WebhookSecretState: string
{
    /** The endpoint's own dedicated field holds at least one secret. */
    case Configured = 'configured';

    /** No dedicated field, but the legacy multi-line field has a line prefixed for this endpoint. */
    case LegacyScoped = 'legacy_scoped';

    /**
     * Only an UNPREFIXED legacy line exists. It authenticates every
     * endpoint, which is exactly the assumption that broke: each
     * Razorpay endpoint has its own secret, so one shared value can be
     * right for at most one of them.
     */
    case LegacyUnscoped = 'legacy_unscoped';

    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::LegacyScoped => 'Legacy fallback active (scoped line)',
            self::LegacyUnscoped => 'Legacy fallback active (shared secret)',
            self::Missing => 'Not configured',
        };
    }

    /** A delivery to this endpoint can be verified at all. */
    public function isVerifiable(): bool
    {
        return $this !== self::Missing;
    }

    /** Verifiable, but not the production-grade configuration. */
    public function isLegacy(): bool
    {
        return $this === self::LegacyScoped || $this === self::LegacyUnscoped;
    }
}
