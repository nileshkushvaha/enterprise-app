<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_account.disable_after_failed_attempts', true);
        $this->migrator->add('security_account.auto_unlock_after', 30);
        $this->migrator->add('security_account.notify_user', true);
        $this->migrator->add('security_account.notify_admin', false);

        // Future placeholders
        $this->migrator->add('security_account.login_history_enabled', false);
        $this->migrator->add('security_account.suspicious_login_detection', false);
        $this->migrator->add('security_account.ip_restriction_enabled', false);
        $this->migrator->add('security_account.device_restriction_enabled', false);
    }
};
