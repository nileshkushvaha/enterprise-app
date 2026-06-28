<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payment_configuration.currency', 'INR');
        $this->migrator->add('payment_configuration.currency_symbol', '₹');
        $this->migrator->add('payment_configuration.decimal_precision', 2);
        $this->migrator->add('payment_configuration.default_tax_percent', 18.0);
        $this->migrator->add('payment_configuration.invoice_prefix', 'INV');
        $this->migrator->add('payment_configuration.invoice_number_length', 8);
        $this->migrator->add('payment_configuration.payment_due_days', 7);
        $this->migrator->add('payment_configuration.allow_partial_payment', false);
        $this->migrator->add('payment_configuration.auto_generate_invoice', true);
        $this->migrator->add('payment_configuration.auto_capture_payment', true);
        $this->migrator->add('payment_configuration.refund_enabled', true);
    }
};
