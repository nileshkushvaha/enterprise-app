<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Policies\CacheManagerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CacheManagerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $cacheAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        Permission::firstOrCreate(['name' => 'cache_manager.view',     'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cache_manager.clear',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cache_manager.optimize', 'guard_name' => 'web']);

        // Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Users
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        $this->cacheAdmin = User::factory()->create();
        $this->cacheAdmin->givePermissionTo(['cache_manager.view', 'cache_manager.clear', 'cache_manager.optimize']);

        $this->regularUser = User::factory()->create();
    }

    // ── super_admin bypasses everything ──────────────────────────────────

    public function test_super_admin_can_view_page(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('cache_manager.view'));
    }

    public function test_super_admin_can_clear_cache(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('cache_manager.clear'));
    }

    public function test_super_admin_can_optimize(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('cache_manager.optimize'));
    }

    // ── permission-based access ───────────────────────────────────────────

    public function test_user_with_view_permission_can_view_page(): void
    {
        $this->assertTrue(Gate::forUser($this->cacheAdmin)->check('cache_manager.view'));
    }

    public function test_user_with_clear_permission_can_clear_cache(): void
    {
        $this->assertTrue(Gate::forUser($this->cacheAdmin)->check('cache_manager.clear'));
    }

    public function test_user_with_optimize_permission_can_optimize(): void
    {
        $this->assertTrue(Gate::forUser($this->cacheAdmin)->check('cache_manager.optimize'));
    }

    // ── regular user is denied ────────────────────────────────────────────

    public function test_regular_user_cannot_view_page(): void
    {
        $this->assertFalse(Gate::forUser($this->regularUser)->check('cache_manager.view'));
    }

    public function test_regular_user_cannot_clear_cache(): void
    {
        $this->assertFalse(Gate::forUser($this->regularUser)->check('cache_manager.clear'));
    }

    public function test_regular_user_cannot_optimize(): void
    {
        $this->assertFalse(Gate::forUser($this->regularUser)->check('cache_manager.optimize'));
    }

    // ── policy method coverage ────────────────────────────────────────────

    public function test_policy_view_page_grants_access_to_super_admin(): void
    {
        $policy = new CacheManagerPolicy;
        $this->assertTrue($policy->viewPage($this->superAdmin));
    }

    public function test_policy_clear_application_cache_denies_regular_user(): void
    {
        $policy = new CacheManagerPolicy;
        $this->assertFalse($policy->clearApplicationCache($this->regularUser));
    }

    public function test_policy_optimize_denies_regular_user(): void
    {
        $policy = new CacheManagerPolicy;
        $this->assertFalse($policy->optimize($this->regularUser));
    }

    public function test_all_clear_methods_share_same_permission(): void
    {
        $policy = new CacheManagerPolicy;

        $userWithClear = User::factory()->create();
        $userWithClear->givePermissionTo('cache_manager.clear');

        $this->assertTrue($policy->clearViewCache($userWithClear));
        $this->assertTrue($policy->clearRouteCache($userWithClear));
        $this->assertTrue($policy->clearConfigCache($userWithClear));
        $this->assertTrue($policy->clearEventCache($userWithClear));
    }
}
