<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SessionSettings extends Settings
{
    // Session timeout
    public int $idle_timeout; // minutes

    // Devices
    public bool $allow_multiple_sessions;

    public bool $force_logout_on_password_change;

    // Future placeholders
    public bool $trusted_devices_enabled; // future

    public bool $device_management_enabled; // future

    public static function group(): string
    {
        return 'security_session';
    }
}
