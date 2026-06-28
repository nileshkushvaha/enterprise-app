<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_registration.self_registration_enabled', false);
        $this->migrator->add('security_registration.default_role', null);
        $this->migrator->add('security_registration.require_admin_approval', false);
        $this->migrator->add('security_registration.send_welcome_email', true);
        $this->migrator->add('security_registration.auto_verify_email', false);

        // Future placeholders
        $this->migrator->add('security_registration.invitation_only', false);
        $this->migrator->add('security_registration.domain_restriction_enabled', false);
    }
};
