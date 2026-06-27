<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

/**
 * Shared behaviour for all settings pages.
 * Only users with the 'super_admin' role or any 'settings.*' permission
 * can access settings pages.
 */
trait HasSettingsAccess
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admins always have access
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Alternatively: any settings.* permission
        return $user->permissions()
            ->where('name', 'like', 'settings.%')
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
