<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AuthenticationSettings extends Settings
{
    // General
    public bool $login_enabled;

    public bool $remember_me_enabled;

    public bool $email_verification_required;

    public string $default_login_method; // 'email' | 'username' | 'email_or_username'

    // Future placeholders — stored but not enforced yet
    public bool $two_factor_enabled;   // future

    public bool $passkeys_enabled;     // future

    public bool $social_login_enabled; // future

    public bool $ldap_enabled;         // future

    public bool $saml_enabled;         // future

    public bool $azure_ad_enabled;     // future

    public static function group(): string
    {
        return 'security_auth';
    }
}
