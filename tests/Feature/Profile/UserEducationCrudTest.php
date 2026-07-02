<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\EducationLevel;
use App\Models\User;
use App\Models\UserEducation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserEducationCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    // ── Creation ──────────────────────────────────────────────────────────

    public function test_education_can_be_created_via_factory(): void
    {
        $user = User::factory()->create();
        $education = UserEducation::factory()->for($user)->create();

        $this->assertDatabaseHas('user_educations', [
            'user_id' => $user->id,
            'institution_name' => $education->institution_name,
        ]);
    }

    public function test_multiple_educations_can_belong_to_one_user(): void
    {
        $user = User::factory()->create();
        UserEducation::factory()->for($user)->count(3)->create();

        $this->assertSame(3, $user->educations()->count());
    }

    public function test_educations_are_ordered_by_display_order(): void
    {
        $user = User::factory()->create();
        UserEducation::factory()->for($user)->create(['display_order' => 2, 'institution_name' => 'Second Uni']);
        UserEducation::factory()->for($user)->create(['display_order' => 0, 'institution_name' => 'First Uni']);
        UserEducation::factory()->for($user)->create(['display_order' => 5, 'institution_name' => 'Third Uni']);

        $names = $user->educations()->pluck('institution_name')->toArray();

        $this->assertSame(['First Uni', 'Second Uni', 'Third Uni'], $names);
    }

    // ── Soft delete / restore ─────────────────────────────────────────────

    public function test_education_can_be_soft_deleted(): void
    {
        $education = UserEducation::factory()->for(User::factory())->create();

        $education->delete();

        $this->assertSoftDeleted('user_educations', ['id' => $education->id]);
    }

    public function test_soft_deleted_education_can_be_restored(): void
    {
        $education = UserEducation::factory()->for(User::factory())->create();
        $education->delete();

        $education->restore();

        $this->assertNotSoftDeleted('user_educations', ['id' => $education->id]);
    }

    public function test_education_can_be_force_deleted(): void
    {
        $education = UserEducation::factory()->for(User::factory())->create();
        $education->delete();

        $education->forceDelete();

        $this->assertDatabaseMissing('user_educations', ['id' => $education->id]);
    }

    // ── is_current business logic ──────────────────────────────────────────

    public function test_current_education_has_null_end_date(): void
    {
        $education = UserEducation::factory()->for(User::factory())->current()->create();

        $this->assertTrue($education->is_current);
        $this->assertNull($education->end_date);
    }

    public function test_active_scope_filters_by_status(): void
    {
        $user = User::factory()->create();
        UserEducation::factory()->for($user)->create(['status' => 'active']);
        UserEducation::factory()->for($user)->create(['status' => 'inactive']);

        $this->assertSame(1, $user->educations()->active()->count());
    }

    // ── Enum casting ──────────────────────────────────────────────────────

    public function test_education_level_is_cast_to_enum(): void
    {
        $education = UserEducation::factory()->for(User::factory())->create([
            'education_level' => EducationLevel::Master,
        ]);

        $this->assertInstanceOf(EducationLevel::class, $education->fresh()->education_level);
        $this->assertSame(EducationLevel::Master, $education->fresh()->education_level);
    }

    // ── Decimal casting ───────────────────────────────────────────────────

    public function test_percentage_is_stored_and_retrieved_as_numeric(): void
    {
        $education = UserEducation::factory()->for(User::factory())->create(['percentage' => 85.5]);

        $this->assertEquals(85.5, $education->fresh()->percentage);
    }

    // ── created_by / updated_by auto-fill ─────────────────────────────────

    public function test_created_by_is_set_automatically(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $education = UserEducation::factory()->for(User::factory())->create();

        $this->assertSame($admin->id, $education->fresh()->created_by);
    }
}
