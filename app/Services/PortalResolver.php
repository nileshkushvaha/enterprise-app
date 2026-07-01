<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Single source of truth for the Portal Architecture: there is one
 * authentication system (one users table, one guard, one provider) and two
 * portals — Admin (Filament, /admin) and Frontend (Blade, /dashboard).
 *
 * Portal membership:
 *   Admin Portal    — super_admin, manager
 *   Frontend Portal — everyone else (instructor, student, future roles)
 *
 * Only this class decides WHERE a user belongs. It does not decide WHAT
 * they may do once there — that is Policies/permissions, not portal
 * resolution. Do not scatter role checks for routing purposes anywhere else;
 * call this resolver instead. User::isSuperAdmin() is the only role helper
 * permitted outside this class (it is used by Gate::before() and policies
 * for authorization, not portal routing).
 */
final class PortalResolver
{
    public function usesAdminPortal(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('manager');
    }

    public function usesFrontendPortal(User $user): bool
    {
        return ! $this->usesAdminPortal($user);
    }

    /** Where to send the user immediately after successful authentication. */
    public function loginRedirect(User $user): string
    {
        return $this->dashboardRoute($user);
    }

    /** Where to send the user after logout — back to their own portal's login page. */
    public function logoutRedirect(User $user): string
    {
        return $this->usesAdminPortal($user)
            ? route('filament.admin.auth.login')
            : route('auth.login');
    }

    /** The portal's base/home URL. */
    public function homeRoute(User $user): string
    {
        return $this->usesAdminPortal($user) ? '/admin' : route('home');
    }

    /** The portal's dashboard URL. */
    public function dashboardRoute(User $user): string
    {
        return $this->usesAdminPortal($user) ? '/admin' : route('dashboard');
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

        if ($this->usesAdminPortal($user)) {
            $items[] = [
                'label' => 'Admin Panel',
                'url' => $this->homeRoute($user),
                'icon' => 'cog',
                'external' => true,
                'divider' => true,
            ];
        }

        // Admin-portal users already have the "Admin Panel" link above;
        // frontend-portal users get a "Dashboard" link to their portal home.
        if ($this->usesFrontendPortal($user)) {
            $items[] = ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'home'];
        }

        $items[] = [
            'label' => 'My Profile',
            'url' => $this->usesAdminPortal($user)
                ? route('filament.admin.auth.profile')
                : route('profile.show'),
            'icon' => 'user',
        ];
        $items[] = ['label' => 'Security', 'url' => route('profile.show'), 'icon' => 'shield'];

        return $items;
    }
}
