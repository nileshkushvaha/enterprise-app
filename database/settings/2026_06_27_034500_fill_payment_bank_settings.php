<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payment_bank.enable_offline_payment', false);
        $this->migrator->add('payment_bank.account_holder_name', null);
        $this->migrator->add('payment_bank.bank_name', null);
        $this->migrator->add('payment_bank.branch_name', null);
        $this->migrator->add('payment_bank.account_number', null);
        $this->migrator->add('payment_bank.ifsc_code', null);
        $this->migrator->add('payment_bank.swift_code', null);
        $this->migrator->add('payment_bank.iban', null);
        $this->migrator->add('payment_bank.account_type', 'current');
        $this->migrator->add('payment_bank.upi_id', null);
        $this->migrator->add('payment_bank.qr_code_image', null);
        $this->migrator->add('payment_bank.payment_instructions', null);
        $this->migrator->add('payment_bank.display_on_invoice', true);
        $this->migrator->add('payment_bank.display_on_payment_page', true);
    }
};
