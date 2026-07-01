<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\PortalResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardResolverTest extends TestCase
{
    use RefreshDatabase;

    private PortalResolver $portal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
        $this->portal = app(PortalResolver::class);
    }

    // ── usesAdminPortal ───────────────────────────────────────────────────────

    public function test_super_admin_uses_admin_portal(): void
    {
        $user = $this->superAdmin();

        $this->assertTrue($this->portal->usesAdminPortal($user));
    }

    public function test_manager_uses_admin_portal(): void
    {
        $user = $this->manager();

        $this->assertTrue($this->portal->usesAdminPortal($user));
    }

    public function test_user_id_1_without_admin_role_does_not_use_admin_portal(): void
    {
        $user = User::factory()->create([
            'id' => 1,
            'password' => Hash::make('password'),
        ]);

        $this->assertFalse($this->portal->usesAdminPortal($user));
    }

    public function test_student_uses_frontend_portal(): void
    {
        $user = $this->student();

        $this->assertFalse($this->portal->usesAdminPortal($user));
        $this->assertTrue($this->portal->usesFrontendPortal($user));
    }

    public function test_regular_user_without_role_uses_frontend_portal(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->portal->usesAdminPortal($user));
        $this->assertTrue($this->portal->usesFrontendPortal($user));
    }

    // ── loginRedirect ─────────────────────────────────────────────────────────

    public function test_super_admin_redirected_to_admin(): void
    {
        $user = $this->superAdmin();

        $this->assertSame('/admin', $this->portal->loginRedirect($user));
    }

    public function test_manager_redirected_to_admin(): void
    {
        $user = $this->manager();

        $this->assertSame('/admin', $this->portal->loginRedirect($user));
    }

    public function test_student_redirected_to_dashboard(): void
    {
        $user = $this->student();

        $this->assertSame(route('dashboard'), $this->portal->loginRedirect($user));
    }

    // ── logoutRedirect ────────────────────────────────────────────────────────

    public function test_super_admin_logout_goes_to_admin_login(): void
    {
        $user = $this->superAdmin();

        $this->assertSame(route('filament.admin.auth.login'), $this->portal->logoutRedirect($user));
    }

    public function test_student_logout_goes_to_frontend_login(): void
    {
        $user = $this->student();

        $this->assertSame(route('auth.login'), $this->portal->logoutRedirect($user));
    }

    // ── frontendMenu ──────────────────────────────────────────────────────────

    public function test_frontend_menu_has_dashboard_item(): void
    {
        $user = $this->student();
        $menu = $this->portal->frontendMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertContains('Dashboard', $labels);
    }

    public function test_frontend_menu_items_have_url_and_label(): void
    {
        $user = $this->student();

        foreach ($this->portal->frontendMenu($user) as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertNotEmpty($item['url']);
        }
    }

    // ── profileMenu ───────────────────────────────────────────────────────────

    public function test_profile_menu_contains_admin_panel_link_for_super_admin(): void
    {
        $user = $this->superAdmin();
        $menu = $this->portal->profileMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertContains('Admin Panel', $labels);
    }

    public function test_profile_menu_contains_admin_panel_link_for_manager(): void
    {
        $user = $this->manager();
        $menu = $this->portal->profileMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertContains('Admin Panel', $labels);
    }

    public function test_profile_menu_does_not_contain_admin_panel_link_for_student(): void
    {
        $user = $this->student();
        $menu = $this->portal->profileMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertNotContains('Admin Panel', $labels);
    }

    public function test_profile_menu_contains_my_profile_for_all_users(): void
    {
        foreach ([$this->superAdmin(), $this->manager(), $this->student()] as $user) {
            $labels = array_column($this->portal->profileMenu($user), 'label');
            $this->assertContains('My Profile', $labels);
        }
    }

    // ── Dashboard route redirects correctly ───────────────────────────────────

    public function test_admin_portal_user_visiting_dashboard_route_is_redirected_to_admin(): void
    {
        foreach ([$this->superAdmin(), $this->manager()] as $user) {
            $this->actingAs($user);
            $this->get(route('dashboard'))->assertRedirect('/admin');
        }
    }

    public function test_student_visiting_dashboard_route_sees_dashboard_view(): void
    {
        $user = $this->student();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertSuccessful()->assertViewIs('dashboard.index');
    }

    public function test_guest_visiting_dashboard_route_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function manager(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function student(): User
    {
        $role = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
