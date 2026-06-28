<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_login.max_failed_attempts', 5);
        $this->migrator->add('security_login.lockout_duration', 15);

        $this->migrator->add('security_login.throttling_enabled', true);
        $this->migrator->add('security_login.reset_throttling_enabled', true);

        $this->migrator->add('security_login.notify_user_on_failed', true);
        $this->migrator->add('security_login.notify_admin_on_lock', false);

        // Future placeholders
        $this->migrator->add('security_login.recaptcha_enabled', false);
        $this->migrator->add('security_login.turnstile_enabled', false);
    }
};
