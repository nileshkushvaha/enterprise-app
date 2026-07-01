<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Services\PortalResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end coverage of the Portal Architecture:
 *   - ONE authentication system (same users table, one guard)
 *   - TWO portals: Admin (Filament /admin) and Frontend (Blade /dashboard)
 *   - Portal membership is decided exclusively by PortalResolver
 *
 * Not tested here (covered by other suites): policies, permission checks,
 * Gate::before() bypass — those live in SuperAdminAuthorizationTest.
 */
class PortalArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student',    'guard_name' => 'web']);
    }

    // ── Frontend login: one door per portal ──────────────────────────────────

    public function test_super_admin_at_frontend_login_is_redirected_to_admin_login(): void
    {
        $user = $this->makeUser('super_admin');

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_manager_at_frontend_login_is_redirected_to_admin_login(): void
    {
        $user = $this->makeUser('manager');

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_instructor_login_redirects_to_frontend_dashboard(): void
    {
        $user = $this->makeUser('instructor');

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_student_login_redirects_to_frontend_dashboard(): void
    {
        $user = $this->makeUser('student');

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    // ── Admin Panel access (/admin) ───────────────────────────────────────────

    public function test_super_admin_can_access_admin_panel(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_manager_can_access_admin_panel(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_instructor_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->makeUser('instructor'))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_student_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->makeUser('student'))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_panel_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    // ── Frontend Dashboard (/dashboard) ───────────────────────────────────────

    public function test_instructor_can_access_frontend_dashboard(): void
    {
        $this->actingAs($this->makeUser('instructor'))
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertViewIs('dashboard.index');
    }

    public function test_student_can_access_frontend_dashboard(): void
    {
        $this->actingAs($this->makeUser('student'))
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertViewIs('dashboard.index');
    }

    public function test_super_admin_visiting_dashboard_is_redirected_to_admin(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->get(route('dashboard'))
            ->assertRedirect('/admin');
    }

    public function test_manager_visiting_dashboard_is_redirected_to_admin(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get(route('dashboard'))
            ->assertRedirect('/admin');
    }

    public function test_guest_visiting_dashboard_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect('/login');
    }

    // ── Logout destination ────────────────────────────────────────────────────

    public function test_frontend_user_logout_returns_to_frontend_login(): void
    {
        $this->actingAs($this->makeUser('student'))
            ->post(route('auth.logout'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_admin_user_logout_via_frontend_route_returns_to_admin_login(): void
    {
        // Admin-portal users rarely hit this route (they use Filament's
        // /admin/logout instead), but if they do, logoutRedirect still
        // correctly sends them to the admin login.
        $this->actingAs($this->makeUser('super_admin'))
            ->post(route('auth.logout'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    // ── PortalResolver is the only decision point ─────────────────────────────

    public function test_portal_resolver_accepts_manager_as_admin_portal_role(): void
    {
        $resolver = app(PortalResolver::class);

        $this->assertTrue($resolver->usesAdminPortal($this->makeUser('manager')));
    }

    public function test_portal_resolver_accepts_super_admin_as_admin_portal_role(): void
    {
        $resolver = app(PortalResolver::class);

        $this->assertTrue($resolver->usesAdminPortal($this->makeUser('super_admin')));
    }

    public function test_portal_resolver_routes_instructor_to_frontend_portal(): void
    {
        $resolver = app(PortalResolver::class);

        $this->assertTrue($resolver->usesFrontendPortal($this->makeUser('instructor')));
    }

    public function test_portal_resolver_routes_student_to_frontend_portal(): void
    {
        $resolver = app(PortalResolver::class);

        $this->assertTrue($resolver->usesFrontendPortal($this->makeUser('student')));
    }

    public function test_can_access_panel_delegates_to_portal_resolver(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->makeUser('super_admin')->canAccessPanel($panel));
        $this->assertTrue($this->makeUser('manager')->canAccessPanel($panel));
        $this->assertFalse($this->makeUser('instructor')->canAccessPanel($panel));
        $this->assertFalse($this->makeUser('student')->canAccessPanel($panel));
    }

    public function test_ensure_frontend_portal_middleware_delegates_to_portal_resolver(): void
    {
        // Admin-portal user hitting /dashboard must be redirected away — the
        // middleware asks PortalResolver and never checks roles itself.
        $this->actingAs($this->makeUser('manager'))
            ->get(route('dashboard'))
            ->assertRedirect('/admin');
    }

    // ── Admin login: no session created for rejected users ────────────────────

    public function test_admin_login_rejects_frontend_user_and_no_session_created(): void
    {
        $instructor = $this->makeUser('instructor');

        // Filament's login page is a Livewire component — use Livewire::test()
        // to exercise the custom authenticate() override directly. The guard
        // must report no authenticated user after the rejected attempt.
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $instructor->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_admin_login_rejects_student_and_no_session_created(): void
    {
        $student = $this->makeUser('student');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $student->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_admin_login_succeeds_for_super_admin(): void
    {
        $superAdmin = $this->makeUser('super_admin');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $superAdmin->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_admin_login_succeeds_for_manager(): void
    {
        $manager = $this->makeUser('manager');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $manager->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($manager);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
