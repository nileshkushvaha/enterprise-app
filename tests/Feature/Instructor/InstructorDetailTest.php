<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:User', 'guard_name' => 'web']);
    }

    private function makeInstructor(array $profileOverrides = []): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->profile->update(array_merge(['profile_visibility' => 'public'], $profileOverrides));
        $user->assignRole('instructor');

        return $user;
    }

    public function test_detail_page_loads_for_public_instructor(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))->assertOk();
    }

    public function test_instructor_name_shown_on_detail_page(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee($instructor->name);
    }

    public function test_non_instructor_user_returns_404(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->profile->update(['profile_visibility' => 'public']);

        $this->get(route('instructors.show', $user))->assertNotFound();
    }

    public function test_guest_cannot_view_private_instructor_profile(): void
    {
        $instructor = $this->makeInstructor(['profile_visibility' => 'private']);

        $this->get(route('instructors.show', $instructor))->assertForbidden();
    }

    public function test_owner_can_view_their_own_private_profile(): void
    {
        $instructor = $this->makeInstructor(['profile_visibility' => 'private']);

        $this->actingAs($instructor)
            ->get(route('instructors.show', $instructor))
            ->assertOk();
    }

    public function test_admin_with_update_user_can_view_private_profile(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('Update:User');
        $instructor = $this->makeInstructor(['profile_visibility' => 'private']);

        $this->actingAs($admin)
            ->get(route('instructors.show', $instructor))
            ->assertOk();
    }

    public function test_guest_cannot_view_members_only_instructor_profile(): void
    {
        $instructor = $this->makeInstructor(['profile_visibility' => 'members_only']);

        $this->get(route('instructors.show', $instructor))->assertForbidden();
    }

    public function test_authenticated_user_can_view_members_only_profile(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $instructor = $this->makeInstructor(['profile_visibility' => 'members_only']);

        $this->actingAs($viewer)
            ->get(route('instructors.show', $instructor))
            ->assertOk();
    }

    public function test_detail_page_has_og_meta_tag(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('og:title', false);
    }

    public function test_detail_page_has_json_ld_structured_data(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('Person', false);
    }

    public function test_experience_is_shown_on_detail_page(): void
    {
        $instructor = $this->makeInstructor();
        UserExperience::factory()->for($instructor)->create([
            'organization_name' => 'Tech Corp',
            'designation' => 'Senior Dev',
            'is_current' => true,
            'end_date' => null,
        ]);

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('Tech Corp');
    }

    public function test_courses_stub_rendered(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('Courses coming soon');
    }

    public function test_reviews_stub_rendered(): void
    {
        $instructor = $this->makeInstructor();

        $this->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('No reviews yet');
    }

    public function test_stat_bar_shows_zero_courses(): void
    {
        $instructor = $this->makeInstructor();

        $response = $this->get(route('instructors.show', $instructor));

        $response->assertOk()->assertSee('Courses');
    }
}
