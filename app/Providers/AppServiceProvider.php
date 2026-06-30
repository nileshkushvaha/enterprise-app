<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\SchedulerHistory;
use App\Models\Tag;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\PageObserver;
use App\Observers\PostCategoryObserver;
use App\Observers\PostObserver;
use App\Observers\TagObserver;
use App\Policies\ActivityLogPolicy;
use App\Policies\CacheManagerPolicy;
use App\Policies\NavigationMenuPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\QueueMonitorPolicy;
use App\Policies\RolePolicy;
use App\Policies\SchedulerMonitorPolicy;
use App\Policies\Security\SecurityPolicy;
use App\Settings\LoginSecuritySettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->registerSchedulerHistoryListeners();
        $this->registerRateLimiters();
        $this->guardAgainstDestructiveDatabaseCommands();
    }

    /**
     * Block migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback, and db:wipe
     * outside the testing environment, regardless of how they're triggered (CLI, IDE test
     * runner, RefreshDatabase, etc.) — enterprise_app is not recoverable.
     */
    private function guardAgainstDestructiveDatabaseCommands(): void
    {
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));
    }

    private function registerObservers(): void
    {
        Activity::observe(ActivityObserver::class);
        Page::observe(PageObserver::class);
        Post::observe(PostObserver::class);
        PostCategory::observe(PostCategoryObserver::class);
        Tag::observe(TagObserver::class);
    }

    private function registerPolicies(): void
    {
        // App\Models\User is auto-discovered to App\Policies\UserPolicy by Laravel's
        // policy convention — do not bind it to ProfilePolicy here. ProfilePolicy only
        // implements view/update/changePassword (for the profile.* named gates below);
        // binding it as the model policy shadows UserPolicy's CRUD checks and made
        // Filament default-allow full User management (view/create/delete any account)
        // to every authenticated user, since Filament treats "no matching policy method"
        // as allowed rather than denied.
        Gate::policy(NavigationMenu::class, NavigationMenuPolicy::class);
        Gate::policy(Activity::class, ActivityLogPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Gate::define('cache_manager.view', [CacheManagerPolicy::class, 'viewPage']);
        Gate::define('cache_manager.clear', [CacheManagerPolicy::class, 'clearApplicationCache']);
        Gate::define('cache_manager.optimize', [CacheManagerPolicy::class, 'optimize']);

        Gate::define('scheduler_monitor.view', [SchedulerMonitorPolicy::class, 'viewPage']);
        Gate::define('scheduler_monitor.run', [SchedulerMonitorPolicy::class, 'runTask']);

        Gate::define('queue_monitor.view', [QueueMonitorPolicy::class, 'viewPage']);

        Gate::define('profile.view', [ProfilePolicy::class, 'view']);
        Gate::define('profile.update', [ProfilePolicy::class, 'update']);
        Gate::define('password.change', [ProfilePolicy::class, 'changePassword']);

        Gate::define('security.authentication.view', [SecurityPolicy::class, 'viewAuthentication']);
        Gate::define('security.authentication.update', [SecurityPolicy::class, 'updateAuthentication']);
        Gate::define('security.password_policy.view', [SecurityPolicy::class, 'viewPasswordPolicy']);
        Gate::define('security.password_policy.update', [SecurityPolicy::class, 'updatePasswordPolicy']);
        Gate::define('security.login_security.view', [SecurityPolicy::class, 'viewLoginSecurity']);
        Gate::define('security.login_security.update', [SecurityPolicy::class, 'updateLoginSecurity']);
        Gate::define('security.session.view', [SecurityPolicy::class, 'viewSession']);
        Gate::define('security.session.update', [SecurityPolicy::class, 'updateSession']);
        Gate::define('security.registration.view', [SecurityPolicy::class, 'viewRegistration']);
        Gate::define('security.registration.update', [SecurityPolicy::class, 'updateRegistration']);
        Gate::define('security.account_protection.view', [SecurityPolicy::class, 'viewAccountProtection']);
        Gate::define('security.account_protection.update', [SecurityPolicy::class, 'updateAccountProtection']);

        Gate::define('security.login_history.view', [SecurityPolicy::class, 'viewLoginHistory']);
    }

    /**
     * Record scheduler execution history automatically so the monitor always
     * has data even when tasks run via cron (not "Run Now").
     */
    private function registerSchedulerHistoryListeners(): void
    {
        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event): void {
            SchedulerHistory::create([
                'command' => $event->task->command ?? 'closure',
                'triggered_by' => 'scheduler',
                'status' => 'success',
                'duration_ms' => (int) ($event->runtime * 1000),
                'ran_at' => now(),
            ]);
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            SchedulerHistory::create([
                'command' => $event->task->command ?? 'closure',
                'triggered_by' => 'scheduler',
                'status' => 'failed',
                'output' => $event->exception->getMessage(),
                'ran_at' => now(),
            ]);
        });

        Event::listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $event): void {
            SchedulerHistory::create([
                'command' => $event->task->command ?? 'closure',
                'triggered_by' => 'scheduler',
                'status' => 'skipped',
                'ran_at' => now(),
            ]);
        });
    }

    /**
     * Grant the super_admin role unrestricted access to every Gate ability.
     * This runs before any policy or Gate check, so it short-circuits everything.
     *
     * Deliberately keyed on role NAME, not database ID — an ID-based check
     * (e.g. "role id 1") would silently grant unrestricted access to whatever
     * role happens to occupy that row after a reseed/migration, regardless of
     * its actual name or assigned permissions.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }

    /**
     * Auto-assign every newly created permission to the super_admin role.
     * This ensures super_admin always has all permissions, even after shield:generate.
     *
     * Looked up by name, not ID — the roles table is the single source of
     * truth for "what is super_admin", and role IDs are never authoritative.
     */
    private function registerPermissionObserver(): void
    {
        Permission::created(function (Permission $permission): void {
            $superAdmin = Role::where('name', 'super_admin')->first();

            if ($superAdmin) {
                $superAdmin->givePermissionTo($permission);
            }
        });
    }

    /**
     * Named rate limiters — evaluated per request so settings changes take effect immediately.
     * Routes reference these by name: throttle:login and throttle:password.reset
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $settings = app(LoginSecuritySettings::class);

            if (! $settings->throttling_enabled) {
                return [];
            }

            return Limit::perMinute(10)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('password.reset', function (Request $request) {
            $settings = app(LoginSecuritySettings::class);

            if (! $settings->reset_throttling_enabled) {
                return [];
            }

            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
