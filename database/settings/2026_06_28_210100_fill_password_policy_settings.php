<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_password.min_length', 8);
        $this->migrator->add('security_password.require_uppercase', true);
        $this->migrator->add('security_password.require_lowercase', true);
        $this->migrator->add('security_password.require_number', true);
        $this->migrator->add('security_password.require_special', false);

        $this->migrator->add('security_password.prevent_reuse', false);
        $this->migrator->add('security_password.password_history_count', 5);

        $this->migrator->add('security_password.expiry_enabled', false);
        $this->migrator->add('security_password.expiry_days', 90);

        // Future placeholder
        $this->migrator->add('security_password.force_change_on_first_login', false);
    }
};
