<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\DashboardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardResolverTest extends TestCase
{
    use RefreshDatabase;

    private DashboardResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
        $this->resolver = app(DashboardResolver::class);
    }

    // ── isAdminPanel ──────────────────────────────────────────────────────────

    public function test_super_admin_goes_to_admin_panel(): void
    {
        $user = $this->superAdmin();

        $this->assertTrue($this->resolver->isAdminPanel($user));
    }

    public function test_user_id_1_goes_to_admin_panel(): void
    {
        // Create user so the DB auto-increments to ID 1
        $user = User::factory()->create([
            'id' => 1,
            'password' => Hash::make('password'),
        ]);

        $this->assertTrue($this->resolver->isAdminPanel($user));
    }

    public function test_student_goes_to_frontend_dashboard(): void
    {
        $user = $this->student();

        $this->assertFalse($this->resolver->isAdminPanel($user));
    }

    public function test_regular_user_without_role_goes_to_frontend_dashboard(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->resolver->isAdminPanel($user));
    }

    // ── redirectAfterLogin ────────────────────────────────────────────────────

    public function test_super_admin_redirected_to_admin(): void
    {
        $user = $this->superAdmin();

        $this->assertSame('/admin', $this->resolver->redirectAfterLogin($user));
    }

    public function test_student_redirected_to_dashboard(): void
    {
        $user = $this->student();

        $this->assertSame(route('dashboard'), $this->resolver->redirectAfterLogin($user));
    }

    // ── frontendMenu ──────────────────────────────────────────────────────────

    public function test_frontend_menu_has_dashboard_item(): void
    {
        $user = $this->student();
        $menu = $this->resolver->frontendMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertContains('Dashboard', $labels);
    }

    public function test_frontend_menu_items_have_url_and_label(): void
    {
        $user = $this->student();

        foreach ($this->resolver->frontendMenu($user) as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertNotEmpty($item['url']);
        }
    }

    // ── profileMenu ───────────────────────────────────────────────────────────

    public function test_profile_menu_contains_admin_panel_link_for_super_admin(): void
    {
        $user = $this->superAdmin();
        $menu = $this->resolver->profileMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertContains('Admin Panel', $labels);
    }

    public function test_profile_menu_does_not_contain_admin_panel_link_for_student(): void
    {
        $user = $this->student();
        $menu = $this->resolver->profileMenu($user);

        $labels = array_column($menu, 'label');
        $this->assertNotContains('Admin Panel', $labels);
    }

    public function test_profile_menu_contains_my_profile_for_all_users(): void
    {
        foreach ([$this->superAdmin(), $this->student()] as $user) {
            $labels = array_column($this->resolver->profileMenu($user), 'label');
            $this->assertContains('My Profile', $labels);
        }
    }

    // ── Dashboard route redirects correctly ───────────────────────────────────

    public function test_admin_visiting_dashboard_route_is_redirected_to_admin_panel(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect('/admin');
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
