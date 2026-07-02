<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\EmploymentType;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserExperienceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:User', 'guard_name' => 'web']);
    }

    // ── Creation ──────────────────────────────────────────────────────────

    public function test_experience_can_be_created_via_factory(): void
    {
        $user = User::factory()->create();
        $experience = UserExperience::factory()->for($user)->create();

        $this->assertDatabaseHas('user_experiences', [
            'user_id' => $user->id,
            'organization_name' => $experience->organization_name,
        ]);
    }

    public function test_multiple_experiences_can_belong_to_one_user(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->count(3)->create();

        $this->assertSame(3, $user->experiences()->count());
    }

    public function test_experiences_are_ordered_by_display_order(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create(['display_order' => 2, 'organization_name' => 'Second']);
        UserExperience::factory()->for($user)->create(['display_order' => 0, 'organization_name' => 'First']);
        UserExperience::factory()->for($user)->create(['display_order' => 5, 'organization_name' => 'Third']);

        $names = $user->experiences()->pluck('organization_name')->toArray();

        $this->assertSame(['First', 'Second', 'Third'], $names);
    }

    // ── Soft delete / restore ─────────────────────────────────────────────

    public function test_experience_can_be_soft_deleted(): void
    {
        $experience = UserExperience::factory()->for(User::factory())->create();

        $experience->delete();

        $this->assertSoftDeleted('user_experiences', ['id' => $experience->id]);
    }

    public function test_soft_deleted_experience_is_excluded_from_active_scope(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create();
        $deleted = UserExperience::factory()->for($user)->create();
        $deleted->delete();

        $this->assertSame(1, $user->experiences()->count());
    }

    public function test_soft_deleted_experience_can_be_restored(): void
    {
        $experience = UserExperience::factory()->for(User::factory())->create();
        $experience->delete();

        $experience->restore();

        $this->assertNotSoftDeleted('user_experiences', ['id' => $experience->id]);
    }

    public function test_experience_can_be_force_deleted(): void
    {
        $experience = UserExperience::factory()->for(User::factory())->create();
        $experience->delete();

        $experience->forceDelete();

        $this->assertDatabaseMissing('user_experiences', ['id' => $experience->id]);
    }

    // ── is_current / end_date business logic ──────────────────────────────

    public function test_is_current_true_stores_null_end_date(): void
    {
        $experience = UserExperience::factory()->for(User::factory())->create([
            'is_current' => true,
            'end_date' => null,
        ]);

        $this->assertTrue($experience->is_current);
        $this->assertNull($experience->end_date);
    }

    public function test_active_scope_filters_by_status(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create(['status' => 'active']);
        UserExperience::factory()->for($user)->create(['status' => 'inactive']);

        $this->assertSame(1, $user->experiences()->active()->count());
    }

    // ── Enum casting ──────────────────────────────────────────────────────

    public function test_employment_type_is_cast_to_enum(): void
    {
        $experience = UserExperience::factory()->for(User::factory())->create([
            'employment_type' => EmploymentType::Contract,
        ]);

        $this->assertInstanceOf(EmploymentType::class, $experience->fresh()->employment_type);
        $this->assertSame(EmploymentType::Contract, $experience->fresh()->employment_type);
    }

    // ── Skills JSON ───────────────────────────────────────────────────────

    public function test_skills_field_is_stored_as_json_and_retrieved_as_array(): void
    {
        $skills = ['PHP', 'Laravel', 'Vue'];
        $experience = UserExperience::factory()->for(User::factory())->create([
            'skills' => $skills,
        ]);

        $this->assertSame($skills, $experience->fresh()->skills);
    }

    // ── created_by / updated_by auto-fill ─────────────────────────────────

    public function test_created_by_is_set_when_admin_creates_experience(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $experience = UserExperience::factory()->for(User::factory())->create();

        $this->assertSame($admin->id, $experience->fresh()->created_by);
    }

    public function test_updated_by_is_set_when_admin_updates_experience(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $experience = UserExperience::factory()->for(User::factory())->create();
        $experience->update(['designation' => 'Updated Title']);

        $this->assertSame($admin->id, $experience->fresh()->updated_by);
    }
}
