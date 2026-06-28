<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_session.idle_timeout', 120);
        $this->migrator->add('security_session.allow_multiple_sessions', true);
        $this->migrator->add('security_session.force_logout_on_password_change', true);

        // Future placeholders
        $this->migrator->add('security_session.trusted_devices_enabled', false);
        $this->migrator->add('security_session.device_management_enabled', false);
    }
};
