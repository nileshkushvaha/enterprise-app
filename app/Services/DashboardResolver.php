<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Single source of truth for dashboard routing decisions.
 *
 * Role 1+2 (super_admin / user ID 1) → Filament /admin
 * Role 3+ (student, etc.)            → frontend /dashboard
 */
final class DashboardResolver
{
    public function isAdminPanel(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->id === 1;
    }

    public function redirectAfterLogin(User $user): string
    {
        if ($this->isAdminPanel($user)) {
            return '/admin';
        }

        return route('dashboard');
    }

    /** Nav items for the frontend dashboard sidebar. */
    public function frontendMenu(User $user): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => route('dashboard'),
                'icon' => 'home',
                'route' => 'dashboard',
            ],
            [
                'label' => 'My Profile',
                'url' => route('profile.show'),
                'icon' => 'user',
                'route' => 'profile.*',
            ],
        ];
    }

    /** Items for the header profile dropdown. */
    public function profileMenu(User $user): array
    {
        $items = [];

        if ($this->isAdminPanel($user)) {
            $items[] = [
                'label' => 'Admin Panel',
                'url' => '/admin',
                'icon' => 'cog',
                'external' => true,
                'divider' => true,
            ];
        }

        $items[] = ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'home'];
        $items[] = ['label' => 'My Profile', 'url' => route('profile.show'), 'icon' => 'user'];
        $items[] = ['label' => 'Security', 'url' => route('auth.two-factor.setup'), 'icon' => 'shield'];

        return $items;
    }
}
