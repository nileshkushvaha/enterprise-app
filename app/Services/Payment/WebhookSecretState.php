<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Whether one webhook endpoint's secret is present. Reported to
 * operators (settings page, readiness check, platform:audit-config)
 * without ever revealing the secret itself.
 */
enum WebhookSecretState: string
{
    case Configured = 'configured';

    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::Missing => 'Not configured',
        };
    }
}
