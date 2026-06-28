<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security_auth.login_enabled', true);
        $this->migrator->add('security_auth.remember_me_enabled', true);
        $this->migrator->add('security_auth.email_verification_required', true);
        $this->migrator->add('security_auth.default_login_method', 'email');

        // Future placeholders — stored disabled
        $this->migrator->add('security_auth.two_factor_enabled', false);
        $this->migrator->add('security_auth.passkeys_enabled', false);
        $this->migrator->add('security_auth.social_login_enabled', false);
        $this->migrator->add('security_auth.ldap_enabled', false);
        $this->migrator->add('security_auth.saml_enabled', false);
        $this->migrator->add('security_auth.azure_ad_enabled', false);
    }
};
