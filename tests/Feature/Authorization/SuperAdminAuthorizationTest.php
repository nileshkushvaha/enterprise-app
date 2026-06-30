<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\DashboardResolver;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Locks in the single-source-of-truth authorization standard: the roles
 * table (by role name, never role ID) decides super-admin status — and
 * every authorization path (Gate::before, policies, DashboardResolver)
 * agrees, via User::isSuperAdmin().
 */
class SuperAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER_ADMIN = 'super_admin';

    private const MANAGER = 'manager';

    private const INSTRUCTOR = 'instructor';

    private const STUDENT = 'student';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => self::SUPER_ADMIN, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => self::MANAGER, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => self::INSTRUCTOR, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => self::STUDENT, 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'ViewAny:Role', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:User', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:Post', 'guard_name' => 'web']);
    }

    // ── User::isSuperAdmin() ─────────────────────────────────────────────

    public function test_is_super_admin_true_for_super_admin_role(): void
    {
        $user = $this->makeUser(self::SUPER_ADMIN);

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_false_for_other_system_roles(): void
    {
        foreach ([self::MANAGER, self::INSTRUCTOR, self::STUDENT] as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse($user->isSuperAdmin(), "{$role} must not be treated as super admin");
        }
    }

    public function test_is_super_admin_false_for_user_with_no_roles(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_super_admin_does_not_depend_on_user_id(): void
    {
        // User ID 1 without the role must not be treated as super admin —
        // authorization is decided by the roles table, never by user ID.
        $user = User::factory()->create(['id' => 1, 'status' => 'active']);

        $this->assertFalse($user->isSuperAdmin());
    }

    // ── Gate::before() short-circuit ──────────────────────────────────────

    public function test_gate_before_grants_super_admin_any_ability_including_undefined_ones(): void
    {
        $superAdmin = $this->makeUser(self::SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($superAdmin)->check('some-ability-that-is-never-defined'));
    }

    public function test_gate_before_does_not_grant_non_super_admin_an_undefined_ability(): void
    {
        $manager = $this->makeUser(self::MANAGER);

        $this->assertFalse(Gate::forUser($manager)->check('some-ability-that-is-never-defined'));
    }

    // ── RolePolicy / Gate::policy() registration ──────────────────────────

    public function test_role_model_is_registered_to_role_policy(): void
    {
        $this->assertSame(RolePolicy::class, get_class(Gate::getPolicyFor(Role::class)));
    }

    public function test_super_admin_bypasses_role_policy_via_gate_before(): void
    {
        $superAdmin = $this->makeUser(self::SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($superAdmin)->check('viewAny', Role::class));
    }

    public function test_manager_without_permission_cannot_view_roles(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        // Deliberately not given ViewAny:Role.
        $manager->assignRole(self::MANAGER);

        $this->assertFalse(Gate::forUser($manager)->check('viewAny', Role::class));
    }

    public function test_manager_with_explicit_permission_can_view_roles(): void
    {
        $manager = $this->makeUser(self::MANAGER);
        $manager->givePermissionTo('ViewAny:Role');

        $this->assertTrue(Gate::forUser($manager)->check('viewAny', Role::class));
    }

    // ── Manager / Instructor / Student never get automatic access ─────────

    public function test_manager_instructor_student_have_no_access_without_assigned_permissions(): void
    {
        foreach ([self::MANAGER, self::INSTRUCTOR, self::STUDENT] as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse(
                Gate::forUser($user)->check('viewAny', Role::class),
                "{$role} must not automatically access Role management"
            );
        }
    }

    public function test_instructor_gains_access_only_through_explicit_permission(): void
    {
        $instructor = $this->makeUser(self::INSTRUCTOR);

        $this->assertFalse(Gate::forUser($instructor)->check('ViewAny:Post'));

        $instructor->givePermissionTo('ViewAny:Post');

        $this->assertTrue(Gate::forUser($instructor)->check('ViewAny:Post'));
    }

    public function test_student_gains_access_only_through_explicit_permission(): void
    {
        $student = $this->makeUser(self::STUDENT);

        $this->assertFalse(Gate::forUser($student)->check('ViewAny:User'));

        $student->givePermissionTo('ViewAny:User');

        $this->assertTrue(Gate::forUser($student)->check('ViewAny:User'));
    }

    // ── Admin panel access (FilamentUser::canAccessPanel) ─────────────────

    public function test_super_admin_can_access_panel_without_verified_email(): void
    {
        $superAdmin = $this->makeUser(self::SUPER_ADMIN, verified: false);

        $this->assertTrue($superAdmin->canAccessPanel($this->adminPanel()));
    }

    public function test_non_super_admin_requires_verified_email_to_access_panel(): void
    {
        $manager = $this->makeUser(self::MANAGER, verified: false);

        $this->assertFalse($manager->canAccessPanel($this->adminPanel()));
    }

    public function test_non_super_admin_with_verified_email_can_access_panel(): void
    {
        $manager = $this->makeUser(self::MANAGER, verified: true);

        $this->assertTrue($manager->canAccessPanel($this->adminPanel()));
    }

    // ── DashboardResolver ───────────────────────────────────────────────

    public function test_dashboard_resolver_routes_super_admin_to_admin_panel(): void
    {
        $resolver = app(DashboardResolver::class);
        $superAdmin = $this->makeUser(self::SUPER_ADMIN);

        $this->assertTrue($resolver->isAdminPanel($superAdmin));
        $this->assertSame('/admin', $resolver->redirectAfterLogin($superAdmin));
    }

    public function test_dashboard_resolver_routes_non_super_admins_to_frontend_dashboard(): void
    {
        $resolver = app(DashboardResolver::class);

        foreach ([self::MANAGER, self::INSTRUCTOR, self::STUDENT] as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse($resolver->isAdminPanel($user), "{$role} must not resolve to the admin panel");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeUser(string $role, bool $verified = true): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => $verified ? now() : null,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function adminPanel(): Panel
    {
        return Filament::getPanel('admin');
    }
}
