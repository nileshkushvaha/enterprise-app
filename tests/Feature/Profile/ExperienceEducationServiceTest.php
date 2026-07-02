<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Services\Profile\UserEducationService;
use App\Services\Profile\UserExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceEducationServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserExperienceService $experienceService;

    private UserEducationService $educationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->experienceService = app(UserExperienceService::class);
        $this->educationService = app(UserEducationService::class);
    }

    // ── UserExperienceService ─────────────────────────────────────────────

    public function test_timeline_returns_only_active_experiences(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create(['status' => 'active']);
        UserExperience::factory()->for($user)->create(['status' => 'inactive']);

        $timeline = $this->experienceService->timeline($user);

        $this->assertCount(1, $timeline);
    }

    public function test_current_position_returns_is_current_experience(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create(['is_current' => false]);
        $current = UserExperience::factory()->for($user)->create([
            'is_current' => true,
            'end_date' => null,
        ]);

        $result = $this->experienceService->currentPosition($user);

        $this->assertNotNull($result);
        $this->assertSame($current->id, $result->id);
    }

    public function test_current_position_returns_null_when_no_current_experience(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create(['is_current' => false]);

        $this->assertNull($this->experienceService->currentPosition($user));
    }

    public function test_years_of_experience_sums_across_all_active_experiences(): void
    {
        $user = User::factory()->create();
        UserExperience::factory()->for($user)->create([
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
            'is_current' => false,
        ]);
        UserExperience::factory()->for($user)->create([
            'start_date' => now()->subYear(),
            'is_current' => true,
            'end_date' => null,
        ]);

        $years = $this->experienceService->yearsOfExperience($user);

        $this->assertGreaterThanOrEqual(2.0, $years);
    }

    public function test_years_of_experience_returns_zero_when_no_experiences(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0.0, $this->experienceService->yearsOfExperience($user));
    }

    // ── UserEducationService ──────────────────────────────────────────────

    public function test_education_timeline_returns_only_active_records(): void
    {
        $user = User::factory()->create();
        UserEducation::factory()->for($user)->create(['status' => 'active']);
        UserEducation::factory()->for($user)->create(['status' => 'inactive']);

        $timeline = $this->educationService->timeline($user);

        $this->assertCount(1, $timeline);
    }

    public function test_latest_education_returns_current_record_first(): void
    {
        $user = User::factory()->create();
        UserEducation::factory()->for($user)->create([
            'is_current' => false,
            'end_date' => now()->subYears(5),
        ]);
        $current = UserEducation::factory()->for($user)->create([
            'is_current' => true,
            'end_date' => null,
        ]);

        $latest = $this->educationService->latestEducation($user);

        $this->assertNotNull($latest);
        $this->assertSame($current->id, $latest->id);
    }

    public function test_latest_education_falls_back_to_most_recent_end_date(): void
    {
        $user = User::factory()->create();
        $old = UserEducation::factory()->for($user)->create([
            'is_current' => false,
            'end_date' => now()->subYears(5),
        ]);
        $recent = UserEducation::factory()->for($user)->create([
            'is_current' => false,
            'end_date' => now()->subYear(),
        ]);

        $latest = $this->educationService->latestEducation($user);

        $this->assertSame($recent->id, $latest->id);
    }

    public function test_latest_education_returns_null_when_no_records(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->educationService->latestEducation($user));
    }
}
