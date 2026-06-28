<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AccountProtectionSettings extends Settings
{
    // Account lock
    public bool $disable_after_failed_attempts;

    public int $auto_unlock_after; // minutes (0 = manual unlock only)

    // Notifications
    public bool $notify_user;

    public bool $notify_admin;

    // Future placeholders
    public bool $login_history_enabled;      // future

    public bool $suspicious_login_detection; // future

    public bool $ip_restriction_enabled;     // future

    public bool $device_restriction_enabled; // future

    public static function group(): string
    {
        return 'security_account';
    }
}
