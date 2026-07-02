<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Observers fire on Eloquent events regardless of entry-point (Filament today,
 * frontend tomorrow) — this test exercises them at the model layer directly.
 */
class ExperienceEducationObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    // ── Experience Observer ───────────────────────────────────────────────

    public function test_creating_an_experience_logs_experience_added(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $experience = UserExperience::factory()->for(User::factory())->create([
            'organization_name' => 'Acme Corp',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'experience',
            'event' => 'experience_added',
            'subject_id' => $experience->id,
            'subject_type' => UserExperience::class,
        ]);
    }

    public function test_updating_an_experience_logs_experience_updated(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $experience = UserExperience::factory()->for(User::factory())->create();
        $experience->update(['designation' => 'New Title']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'experience',
            'event' => 'experience_updated',
            'subject_id' => $experience->id,
        ]);
    }

    public function test_deleting_an_experience_logs_experience_deleted(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $experience = UserExperience::factory()->for(User::factory())->create();
        $experience->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'experience',
            'event' => 'experience_deleted',
            'subject_id' => $experience->id,
        ]);
    }

    // ── Education Observer ────────────────────────────────────────────────

    public function test_creating_an_education_logs_education_added(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $education = UserEducation::factory()->for(User::factory())->create([
            'institution_name' => 'MIT',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'education',
            'event' => 'education_added',
            'subject_id' => $education->id,
            'subject_type' => UserEducation::class,
        ]);
    }

    public function test_updating_an_education_logs_education_updated(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $education = UserEducation::factory()->for(User::factory())->create();
        $education->update(['degree' => 'New Degree']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'education',
            'event' => 'education_updated',
        ]);
    }

    public function test_deleting_an_education_logs_education_deleted(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $education = UserEducation::factory()->for(User::factory())->create();
        $education->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'education',
            'event' => 'education_deleted',
        ]);
    }

    // ── No admin notification for experience/education events (by design) ─

    public function test_experience_added_event_does_not_trigger_admin_notification(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        UserExperience::factory()->for(User::factory())->create();

        $this->assertDatabaseMissing('notifications', [
            'type' => 'App\Notifications\Admin\ActivityNotification',
        ]);
    }
}
