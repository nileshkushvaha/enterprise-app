<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LoginSecuritySettings extends Settings
{
    // Login attempts
    public int $max_failed_attempts;

    public int $lockout_duration; // minutes

    // Rate limiting
    public bool $throttling_enabled;

    public bool $reset_throttling_enabled;

    // Notifications
    public bool $notify_user_on_failed;

    public bool $notify_admin_on_lock;

    // Future placeholders
    public bool $recaptcha_enabled;  // future

    public bool $turnstile_enabled;  // future

    public static function group(): string
    {
        return 'security_login';
    }
}
