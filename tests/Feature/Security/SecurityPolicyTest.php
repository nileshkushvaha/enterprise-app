<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $viewUser;

    private User $updateUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $pages = [
            'authentication', 'password_policy', 'login_security',
            'session', 'registration', 'account_protection',
        ];

        foreach ($pages as $page) {
            Permission::firstOrCreate(['name' => "security.{$page}.view",   'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => "security.{$page}.update", 'guard_name' => 'web']);
        }

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->viewUser = User::factory()->create(['status' => 'active']);
        $this->viewUser->givePermissionTo('security.authentication.view');

        $this->updateUser = User::factory()->create(['status' => 'active']);
        $this->updateUser->givePermissionTo([
            'security.authentication.view',
            'security.authentication.update',
        ]);

        $this->regularUser = User::factory()->create(['status' => 'active']);
    }

    // ── super_admin bypasses all policy checks ─────────────────────────────

    public function test_super_admin_can_view_all_pages(): void
    {
        $pages = [
            'authentication', 'password_policy', 'login_security',
            'session', 'registration', 'account_protection',
        ];

        foreach ($pages as $page) {
            $this->assertTrue(
                Gate::forUser($this->superAdmin)->check("security.{$page}.view"),
                "super_admin should be able to view security.{$page}"
            );
        }
    }

    public function test_super_admin_can_update_all_pages(): void
    {
        $pages = [
            'authentication', 'password_policy', 'login_security',
            'session', 'registration', 'account_protection',
        ];

        foreach ($pages as $page) {
            $this->assertTrue(
                Gate::forUser($this->superAdmin)->check("security.{$page}.update"),
                "super_admin should be able to update security.{$page}"
            );
        }
    }

    // ── view vs update are independent ────────────────────────────────────

    public function test_view_permission_allows_view_not_update(): void
    {
        $this->assertTrue(
            Gate::forUser($this->viewUser)->check('security.authentication.view')
        );

        $this->assertFalse(
            Gate::forUser($this->viewUser)->check('security.authentication.update')
        );
    }

    public function test_update_permission_with_view_allows_both(): void
    {
        $this->assertTrue(
            Gate::forUser($this->updateUser)->check('security.authentication.view')
        );

        $this->assertTrue(
            Gate::forUser($this->updateUser)->check('security.authentication.update')
        );
    }

    // ── regular user has no access ─────────────────────────────────────────

    public function test_regular_user_cannot_view_any_security_page(): void
    {
        $pages = [
            'authentication', 'password_policy', 'login_security',
            'session', 'registration', 'account_protection',
        ];

        foreach ($pages as $page) {
            $this->assertFalse(
                Gate::forUser($this->regularUser)->check("security.{$page}.view"),
                "regular user should not be able to view security.{$page}"
            );
        }
    }

    // ── permissions are page-scoped ────────────────────────────────────────

    public function test_view_permission_is_page_scoped(): void
    {
        $scopedUser = User::factory()->create(['status' => 'active']);
        $scopedUser->givePermissionTo('security.authentication.view');

        $this->assertTrue(
            Gate::forUser($scopedUser)->check('security.authentication.view')
        );

        $this->assertFalse(
            Gate::forUser($scopedUser)->check('security.password_policy.view')
        );

        $this->assertFalse(
            Gate::forUser($scopedUser)->check('security.session.view')
        );
    }

    // ── non-existent permission does not crash ────────────────────────────

    public function test_missing_permission_returns_false_not_exception(): void
    {
        $this->assertFalse(
            Gate::forUser($this->regularUser)->check('security.nonexistent.view')
        );
    }
}
