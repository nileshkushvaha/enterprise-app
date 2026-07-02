<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    private function makeInstructor(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $overrides));
        $user->profile->update(['profile_visibility' => 'public']);
        $user->assignRole('instructor');

        return $user;
    }

    public function test_listing_page_loads_for_guest(): void
    {
        $this->get(route('instructors.index'))->assertOk();
    }

    public function test_active_public_instructor_appears_in_listing(): void
    {
        $instructor = $this->makeInstructor(['name' => 'Visible Instructor']);

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertSee('Visible Instructor');
    }

    public function test_inactive_instructor_not_listed(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'name' => 'Inactive Instructor']);
        $user->profile->update(['profile_visibility' => 'public']);
        $user->assignRole('instructor');

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertDontSee('Inactive Instructor');
    }

    public function test_private_profile_instructor_not_listed(): void
    {
        $user = User::factory()->create(['status' => 'active', 'name' => 'Private Instructor']);
        $user->profile->update(['profile_visibility' => 'private']);
        $user->assignRole('instructor');

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertDontSee('Private Instructor');
    }

    public function test_members_only_instructor_not_listed(): void
    {
        $user = User::factory()->create(['status' => 'active', 'name' => 'Members Only Instructor']);
        $user->profile->update(['profile_visibility' => 'members_only']);
        $user->assignRole('instructor');

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertDontSee('Members Only Instructor');
    }

    public function test_non_instructor_user_not_listed(): void
    {
        $user = User::factory()->create(['status' => 'active', 'name' => 'Student Only']);
        $user->profile->update(['profile_visibility' => 'public']);
        $user->assignRole('student');

        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertDontSee('Student Only');
    }

    public function test_keyword_search_filters_by_name(): void
    {
        $this->makeInstructor(['name' => 'Alice Walker']);
        $this->makeInstructor(['name' => 'Bob Builder']);

        $this->get(route('instructors.index', ['q' => 'Alice']))
            ->assertOk()
            ->assertSee('Alice Walker')
            ->assertDontSee('Bob Builder');
    }

    public function test_featured_instructors_appear_first(): void
    {
        $regular = $this->makeInstructor(['name' => 'Regular Instructor']);
        $featured = $this->makeInstructor(['name' => 'Featured Instructor']);
        $featured->profile->update(['is_featured' => true, 'featured_order' => 1]);

        $response = $this->get(route('instructors.index'));
        $content = $response->content();

        $this->assertLessThan(
            strpos($content, 'Regular Instructor'),
            strpos($content, 'Featured Instructor'),
        );
    }

    public function test_empty_state_shown_when_no_instructors(): void
    {
        $this->get(route('instructors.index'))
            ->assertOk()
            ->assertSee('No instructors found');
    }
}
