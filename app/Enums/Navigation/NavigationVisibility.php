<?php

namespace App\Enums\Navigation;

enum NavigationVisibility: string
{
    case All = 'all';
    case Guest = 'guest';
    case Auth = 'auth';
    case Roles = 'roles';
    case Permissions = 'permissions';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Guest => 'Guests only',
            self::Auth => 'Authenticated users',
            self::Roles => 'Specific roles',
            self::Permissions => 'Specific permissions',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::All => 'success',
            self::Guest => 'gray',
            self::Auth => 'info',
            self::Roles => 'warning',
            self::Permissions => 'danger',
        };
    }

    public function requiresPivots(): bool
    {
        return match ($this) {
            self::Roles, self::Permissions => true,
            default => false,
        };
    }
}
