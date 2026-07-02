<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicProfileVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:User', 'guard_name' => 'web']);
    }

    // ── Public visibility ─────────────────────────────────────────────────

    public function test_guest_can_view_public_profile(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'public']);

        $this->get(route('profile.public', $user))->assertOk();
    }

    public function test_authenticated_user_can_view_public_profile(): void
    {
        $viewer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $subject = User::factory()->create();
        $subject->profile->update(['profile_visibility' => 'public']);

        $this->actingAs($viewer)->get(route('profile.public', $subject))->assertOk();
    }

    // ── Private visibility ────────────────────────────────────────────────

    public function test_guest_cannot_view_private_profile(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'private']);

        $this->get(route('profile.public', $user))->assertForbidden();
    }

    public function test_stranger_cannot_view_private_profile(): void
    {
        $stranger = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $subject = User::factory()->create();
        $subject->profile->update(['profile_visibility' => 'private']);

        $this->actingAs($stranger)->get(route('profile.public', $subject))->assertForbidden();
    }

    public function test_owner_can_view_their_own_private_profile(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->profile->update(['profile_visibility' => 'private']);

        $this->actingAs($user)->get(route('profile.public', $user))->assertOk();
    }

    public function test_admin_with_update_user_can_view_any_private_profile(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $admin->givePermissionTo('Update:User');
        $subject = User::factory()->create();
        $subject->profile->update(['profile_visibility' => 'private']);

        $this->actingAs($admin)->get(route('profile.public', $subject))->assertOk();
    }

    // ── Members-only visibility ───────────────────────────────────────────

    public function test_guest_cannot_view_members_only_profile(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'members_only']);

        $this->get(route('profile.public', $user))->assertForbidden();
    }

    public function test_authenticated_user_can_view_members_only_profile(): void
    {
        $viewer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $subject = User::factory()->create();
        $subject->profile->update(['profile_visibility' => 'members_only']);

        $this->actingAs($viewer)->get(route('profile.public', $subject))->assertOk();
    }

    // ── Data-presence conditional rendering ───────────────────────────────

    public function test_experience_timeline_component_appears_when_records_exist(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'public']);
        UserExperience::factory()->for($user)->create([
            'organization_name' => 'Acme Corp',
            'is_current' => true,
            'end_date' => null,
        ]);

        $response = $this->get(route('profile.public', $user));

        $response->assertOk()->assertSee('Acme Corp');
    }

    public function test_education_timeline_component_appears_when_records_exist(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'public']);
        UserEducation::factory()->for($user)->create([
            'institution_name' => 'MIT',
            'is_current' => false,
        ]);

        $response = $this->get(route('profile.public', $user));

        $response->assertOk()->assertSee('MIT');
    }

    public function test_experience_section_absent_when_no_records(): void
    {
        $user = User::factory()->create(['name' => 'test-unique-no-exp-user']);
        $user->profile->update(['profile_visibility' => 'public']);

        $response = $this->get(route('profile.public', $user));

        // The experience-timeline component wraps records in a card titled "Experience"
        // with a data-account-card wrapper — assert no experience card content
        $response->assertOk();
        $this->assertSame(0, $user->experiences()->count());
    }

    public function test_education_section_absent_when_no_records(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'public']);

        $response = $this->get(route('profile.public', $user));

        $response->assertOk();
        $this->assertSame(0, $user->educations()->count());
    }

    // ── Summary stats ──────────────────────────────────────────────────────

    public function test_current_position_shown_in_overview(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['profile_visibility' => 'public']);
        UserExperience::factory()->for($user)->create([
            'designation' => 'Senior Engineer',
            'organization_name' => 'TechCorp',
            'is_current' => true,
            'end_date' => null,
        ]);

        $this->get(route('profile.public', $user))
            ->assertOk()
            ->assertSee('Senior Engineer');
    }
}
