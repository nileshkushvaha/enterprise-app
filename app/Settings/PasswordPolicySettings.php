<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PasswordPolicySettings extends Settings
{
    // Password rules
    public int $min_length;

    public bool $require_uppercase;

    public bool $require_lowercase;

    public bool $require_number;

    public bool $require_special;

    // History — enforcement requires the user_password_histories table (future)
    public bool $prevent_reuse;

    public int $password_history_count;

    // Expiry — enforcement requires password_expires_at on users (future)
    public bool $expiry_enabled;

    public int $expiry_days;

    // Future placeholder
    public bool $force_change_on_first_login; // future

    public static function group(): string
    {
        return 'security_password';
    }
}
