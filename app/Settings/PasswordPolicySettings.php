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

    // History — user_password_histories table
    public bool $prevent_reuse;

    public int $password_history_count;

    // Expiry — uses password_changed_at on users
    public bool $expiry_enabled;

    public int $expiry_days;

    public bool $force_change_on_first_login;

    public static function group(): string
    {
        return 'security_password';
    }
}
