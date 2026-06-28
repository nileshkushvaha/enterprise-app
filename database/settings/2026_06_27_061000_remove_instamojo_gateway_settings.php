<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('payment_gateways.instamojo_enabled');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_sandbox_mode');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_client_id');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_client_secret');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_private_key');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_webhook_secret');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_success_url');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_failure_url');
        $this->migrator->deleteIfExists('payment_gateways.instamojo_webhook_url');
    }
};
