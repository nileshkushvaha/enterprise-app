<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RegistrationSettings extends Settings
{
    // Registration control
    public bool $self_registration_enabled;

    public ?string $default_role;

    public bool $require_admin_approval;

    public bool $send_welcome_email;

    public bool $auto_verify_email;

    // Future placeholders
    public bool $invitation_only; // future

    public bool $domain_restriction_enabled; // future

    public static function group(): string
    {
        return 'security_registration';
    }
}
