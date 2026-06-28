<?php

declare(strict_types=1);

namespace App\Filament\Pages\Security;

use Spatie\Permission\Exceptions\PermissionDoesNotExist;

trait HasSecurityAccess
{
    abstract protected static function securityPermission(): string;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo(static::securityPermission());
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
