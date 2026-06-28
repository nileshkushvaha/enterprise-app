<?php

namespace App\Providers;

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\Observers\PageObserver;
use App\Observers\PostCategoryObserver;
use App\Observers\PostObserver;
use App\Observers\TagObserver;
use App\Policies\NavigationMenuPolicy;
use App\Policies\ProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerSuperAdminGate();
        $this->registerPermissionObserver();
        $this->registerPolicies();
        $this->registerObservers();
    }

    private function registerObservers(): void
    {
        Page::observe(PageObserver::class);
        Post::observe(PostObserver::class);
        PostCategory::observe(PostCategoryObserver::class);
        Tag::observe(TagObserver::class);
    }

    private function registerPolicies(): void
    {
        Gate::policy(User::class, ProfilePolicy::class);
        Gate::policy(NavigationMenu::class, NavigationMenuPolicy::class);

        Gate::define('profile.view', [ProfilePolicy::class, 'view']);
        Gate::define('profile.update', [ProfilePolicy::class, 'update']);
        Gate::define('password.change', [ProfilePolicy::class, 'changePassword']);
    }

    /**
     * Grant super_admin (role ID 1) unrestricted access to every Gate ability.
     * This runs before any policy or Gate check, so it short-circuits everything.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('super_admin')) {
                    return true;
                }

                if ($user->roles()->where('id', 1)->exists()) {
                    return true;
                }
            }

            return null;
        });
    }

    /**
     * Auto-assign every newly created permission to the super_admin role (ID 1).
     * This ensures super_admin always has all permissions, even after shield:generate.
     */
    private function registerPermissionObserver(): void
    {
        Permission::created(function (Permission $permission): void {
            $superAdmin = Role::find(1);

            if ($superAdmin) {
                $superAdmin->givePermissionTo($permission);
            }
        });
    }
}
