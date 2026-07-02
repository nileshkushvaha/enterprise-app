<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Policies\UserEducationPolicy;
use App\Policies\UserExperiencePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization matrix: owner / non-owner / admin-permission / super_admin.
 * Gate::before() covers super_admin automatically — the policy receives no
 * super_admin calls at all (Gate returns true before reaching the policy).
 */
class ExperienceEducationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    private User $adminUser;

    private User $superAdmin;

    private UserExperience $experience;

    private UserEducation $education;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:User', 'guard_name' => 'web']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->stranger = User::factory()->create(['status' => 'active']);

        $this->adminUser = User::factory()->create(['status' => 'active']);
        $this->adminUser->givePermissionTo('Update:User');

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super_admin');

        $this->experience = UserExperience::factory()->for($this->owner)->create();
        $this->education = UserEducation::factory()->for($this->owner)->create();
    }

    // ── Experience Policy ─────────────────────────────────────────────────

    public function test_owner_can_view_their_own_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->check('view', $this->experience));
    }

    public function test_owner_can_update_their_own_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->check('update', $this->experience));
    }

    public function test_owner_can_delete_their_own_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->check('delete', $this->experience));
    }

    public function test_stranger_cannot_view_others_experience(): void
    {
        $this->assertFalse(Gate::forUser($this->stranger)->check('view', $this->experience));
    }

    public function test_stranger_cannot_update_others_experience(): void
    {
        $this->assertFalse(Gate::forUser($this->stranger)->check('update', $this->experience));
    }

    public function test_admin_with_update_user_permission_can_view_any_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->check('view', $this->experience));
    }

    public function test_admin_with_update_user_permission_can_update_any_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->check('update', $this->experience));
    }

    public function test_admin_can_force_delete_experience(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->check('forceDelete', UserExperience::class));
    }

    public function test_stranger_cannot_force_delete_experience(): void
    {
        $this->assertFalse(Gate::forUser($this->stranger)->check('forceDelete', UserExperience::class));
    }

    public function test_super_admin_can_do_everything_with_experience(): void
    {
        // Gate::before() intercepts before the policy — super_admin bypass
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('view', $this->experience));
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('update', $this->experience));
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('forceDelete', UserExperience::class));
    }

    public function test_experience_model_resolves_to_user_experience_policy(): void
    {
        $this->assertInstanceOf(UserExperiencePolicy::class, Gate::getPolicyFor(UserExperience::class));
    }

    // ── Education Policy ──────────────────────────────────────────────────

    public function test_owner_can_view_their_own_education(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->check('view', $this->education));
    }

    public function test_owner_can_update_their_own_education(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->check('update', $this->education));
    }

    public function test_stranger_cannot_view_others_education(): void
    {
        $this->assertFalse(Gate::forUser($this->stranger)->check('view', $this->education));
    }

    public function test_admin_with_update_user_permission_can_manage_any_education(): void
    {
        $this->assertTrue(Gate::forUser($this->adminUser)->check('view', $this->education));
        $this->assertTrue(Gate::forUser($this->adminUser)->check('update', $this->education));
        $this->assertTrue(Gate::forUser($this->adminUser)->check('delete', $this->education));
    }

    public function test_super_admin_can_do_everything_with_education(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('view', $this->education));
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('update', $this->education));
        $this->assertTrue(Gate::forUser($this->superAdmin)->check('forceDelete', UserEducation::class));
    }

    public function test_education_model_resolves_to_user_education_policy(): void
    {
        $this->assertInstanceOf(UserEducationPolicy::class, Gate::getPolicyFor(UserEducation::class));
    }
}
