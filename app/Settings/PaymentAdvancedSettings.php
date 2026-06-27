<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PaymentAdvancedSettings extends Settings
{
    public int $webhook_timeout;
    public bool $retry_failed_payments;
    public bool $queue_payment_events;
    public bool $payment_logging;
    public bool $enable_audit_log;
    public int $max_retry_count;

    public static function group(): string
    {
        return 'payment_advanced';
    }
}

