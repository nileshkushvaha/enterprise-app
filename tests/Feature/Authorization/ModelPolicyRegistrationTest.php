<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Filament treats "no policy" or "policy missing the matching method" as
 * ALLOWED (vendor/filament/filament/src/helpers.php — get_authorization_response()
 * falls through to Gate::before() and defaults to Response::allow() when nothing
 * else answers). That is the opposite of raw Laravel Gate behaviour, so a
 * missing/wrong policy binding silently grants access instead of denying it.
 *
 * This test locks in the two real bugs found from that root cause:
 *   1. Spatie\Permission\Models\Permission had no Gate::policy() binding at all.
 *   2. Gate::policy(User::class, ProfilePolicy::class) shadowed the real
 *      UserPolicy (which has the CRUD methods), letting any authenticated
 *      user manage all User accounts regardless of permissions.
 */
class ModelPolicyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'ViewAny:Permission', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:User', 'guard_name' => 'web']);
    }

    public function test_permission_model_resolves_to_permission_policy(): void
    {
        $this->assertSame(PermissionPolicy::class, get_class(Gate::getPolicyFor(Permission::class)));
    }

    public function test_user_model_resolves_to_user_policy_not_profile_policy(): void
    {
        $this->assertSame(UserPolicy::class, get_class(Gate::getPolicyFor(User::class)));
    }

    public function test_manager_without_view_any_permission_cannot_view_permissions(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');
        // Deliberately not given ViewAny:Permission.

        $this->assertFalse(Gate::forUser($manager)->check('viewAny', Permission::class));
    }

    public function test_manager_with_view_any_permission_can_view_permissions(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');
        $manager->givePermissionTo('ViewAny:Permission');

        $this->assertTrue(Gate::forUser($manager)->check('viewAny', Permission::class));
    }

    public function test_manager_without_view_any_user_permission_cannot_view_users(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');
        // Deliberately not given ViewAny:User.

        $this->assertFalse(Gate::forUser($manager)->check('viewAny', User::class));
    }

    public function test_manager_with_view_any_user_permission_can_view_users(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');
        $manager->givePermissionTo('ViewAny:User');

        $this->assertTrue(Gate::forUser($manager)->check('viewAny', User::class));
    }

    public function test_role_model_still_resolves_to_role_policy(): void
    {
        $this->assertSame(RolePolicy::class, get_class(Gate::getPolicyFor(Role::class)));
    }

    public function test_permission_resource_nav_hidden_for_user_without_permission(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');

        $this->actingAs($manager);

        $this->assertFalse(PermissionResource::canViewAny());
    }

    public function test_user_resource_nav_hidden_for_user_without_permission(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');

        $this->actingAs($manager);

        $this->assertFalse(UserResource::canViewAny());
    }
}
