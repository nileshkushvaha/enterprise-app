<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payment_advanced.webhook_timeout', 30);
        $this->migrator->add('payment_advanced.retry_failed_payments', true);
        $this->migrator->add('payment_advanced.queue_payment_events', true);
        $this->migrator->add('payment_advanced.payment_logging', true);
        $this->migrator->add('payment_advanced.enable_audit_log', true);
        $this->migrator->add('payment_advanced.max_retry_count', 5);
    }
};

