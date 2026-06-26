<?php

namespace App\Providers;

use App\Policies\ProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerSuperAdminGate();
        $this->registerPermissionObserver();
        $this->registerPolicies();
    }

    private function registerPolicies(): void
    {
        Gate::policy(\App\Models\User::class, ProfilePolicy::class);

        // Explicit profile abilities (no model binding needed)
        Gate::define('profile.view',     [ProfilePolicy::class, 'view']);
        Gate::define('profile.update',   [ProfilePolicy::class, 'update']);
        Gate::define('password.change',  [ProfilePolicy::class, 'changePassword']);
    }

    /**
     * Grant super_admin (role ID 1) unrestricted access to every Gate ability.
     * This runs before any policy or Gate check, so it short-circuits everything.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'hasRole')) {
                // Bypass by role name (primary check)
                if ($user->hasRole('super_admin')) {
                    return true;
                }

                // Bypass by role ID 1 (fallback — covers renamed super_admin)
                if ($user->roles()->where('id', 1)->exists()) {
                    return true;
                }
            }

            return null; // Defer to next Gate check
        });
    }

    /**
     * Auto-assign every newly created permission to the super_admin role (ID 1).
     * This ensures super_admin always has all permissions, even after shield:generate.
     */
    private function registerPermissionObserver(): void
    {
        Permission::created(function (Permission $permission): void {
            $superAdmin = \Spatie\Permission\Models\Role::find(1);

            if ($superAdmin) {
                $superAdmin->givePermissionTo($permission);
            }
        });
    }
}
