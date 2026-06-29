<?php

declare(strict_types=1);

namespace App\Policies\Security;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Gate-backed policy for the Security settings pages.
 * Registered in AppServiceProvider via Gate::define().
 *
 * Uses hasPermissionTo() (direct Spatie table check) rather than can() to
 * avoid re-entering the Gate and creating a circular resolution loop.
 *
 * Permission naming convention:
 *   security.<slug>.view   — grants access to view the page
 *   security.<slug>.update — grants permission to save changes
 */
class SecurityPolicy
{
    use HandlesAuthorization;

    // ── Authentication ───────────────────────────────────────────────────

    public function viewAuthentication(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.authentication.view');
    }

    public function updateAuthentication(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.authentication.update');
    }

    // ── Password Policy ──────────────────────────────────────────────────

    public function viewPasswordPolicy(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.password_policy.view');
    }

    public function updatePasswordPolicy(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.password_policy.update');
    }

    // ── Login Security ───────────────────────────────────────────────────

    public function viewLoginSecurity(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.login_security.view');
    }

    public function updateLoginSecurity(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.login_security.update');
    }

    // ── Session ──────────────────────────────────────────────────────────

    public function viewSession(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.session.view');
    }

    public function updateSession(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.session.update');
    }

    // ── Registration ─────────────────────────────────────────────────────

    public function viewRegistration(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.registration.view');
    }

    public function updateRegistration(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.registration.update');
    }

    // ── Account Protection ───────────────────────────────────────────────

    public function viewAccountProtection(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.account_protection.view');
    }

    public function updateAccountProtection(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.account_protection.update');
    }

    // ── Login History ────────────────────────────────────────────────────

    public function viewLoginHistory(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'security.login_history.view');
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    private function isSuperAdmin(AuthUser $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    private function hasPermission(AuthUser $user, string $permission): bool
    {
        try {
            return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
