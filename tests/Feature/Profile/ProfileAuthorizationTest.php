<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Users may only ever edit their own profile — ProfileController operates
 * exclusively on auth()->user(), so there's no user-id parameter to guard
 * against; a logged-in user simply cannot reach anyone else's profile data
 * through the frontend. Admin-side access to any user's profile goes
 * through the existing Shield-driven UserPolicy (Update:User), unchanged.
 */
class ProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_profile_page(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('auth.login'));
    }

    public function test_guest_cannot_update_a_profile(): void
    {
        $this->post(route('profile.update'), ['first_name' => 'Hacker'])
            ->assertRedirect(route('auth.login'));
    }

    public function test_inactive_user_cannot_view_the_profile_page(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($user)->get(route('profile.show'))->assertRedirect();
    }

    public function test_profile_update_only_touches_the_authenticated_users_own_profile(): void
    {
        $userA = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $userB = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($userA)->post(route('profile.update'), ['first_name' => 'OnlyMe']);

        $this->assertSame('OnlyMe', $userA->fresh()->first_name);
        $this->assertNotSame('OnlyMe', $userB->fresh()->first_name);
    }

    public function test_manager_without_update_user_permission_cannot_edit_users_in_admin(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');

        $this->assertFalse($manager->can('Update:User'));
    }

    public function test_manager_with_update_user_permission_can_edit_users_in_admin(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:User', 'guard_name' => 'web']);
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');
        $manager->givePermissionTo('Update:User');

        $this->assertTrue($manager->can('Update:User'));
    }
}
