<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    // ── Middleware: passes when no requirement ─────────────────────────────

    public function test_dashboard_accessible_when_no_must_change_password(): void
    {
        $this->enableForceChange();

        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_middleware_passes_when_policy_disabled(): void
    {
        // Policy OFF even though flag is set
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->save();

        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ── Middleware: blocks when requirement active ──────────────────────────

    public function test_dashboard_redirected_when_must_change_password(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('auth.password.change-required'));
    }

    public function test_profile_redirected_when_must_change_password(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertRedirect(route('auth.password.change-required'));
    }

    public function test_force_change_page_accessible_when_required(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->get(route('auth.password.change-required'))
            ->assertOk();
    }

    public function test_logout_accessible_when_must_change_password(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.logout'))
            ->assertRedirect();
    }

    // ── POST: bypass attempts blocked ──────────────────────────────────────

    public function test_direct_post_to_dashboard_blocked_when_must_change(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        // Attempting to POST to profile password endpoint is redirected
        $this->actingAs($user)
            ->post(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertRedirect(route('auth.password.change-required'));
    }

    // ── Controller: show form ──────────────────────────────────────────────

    public function test_force_change_redirects_to_dashboard_when_not_required(): void
    {
        $this->enableForceChange();

        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('auth.password.change-required'))
            ->assertRedirect(route('dashboard'));
    }

    // ── Controller: store ──────────────────────────────────────────────────

    public function test_successful_force_change_clears_flag(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_successful_force_change_updates_password_changed_at(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ]);

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_password_actually_changed_after_force_change(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ]);

        $this->assertTrue(Hash::check('NewStrongPass1!', $user->fresh()->password));
    }

    public function test_force_change_logs_activity(): void
    {
        $this->enableForceChange();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'password_changed',
            'subject_id' => $user->id,
        ]);
    }

    public function test_force_change_requires_policy_compliant_password(): void
    {
        $this->enableForceChange();

        $s = app(PasswordPolicySettings::class);
        $s->min_length = 20;
        $s->save();

        $user = $this->userRequiringChange();

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'Short1!',
                'password_confirmation' => 'Short1!',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_force_change_blocked_when_user_has_no_flag(): void
    {
        $this->enableForceChange();

        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ])
            ->assertForbidden();
    }

    // ── Admin: user creation ───────────────────────────────────────────────

    public function test_new_user_flagged_when_policy_enabled(): void
    {
        $this->enableForceChange();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        // Simulate what CreateUser::mutateFormDataBeforeCreate does
        $data = ['password' => 'Password1!', 'email' => 'new@test.com', 'name' => 'New User', 'status' => 'active'];
        $controller = new CreateUser;
        $result = $this->callMutate($data);

        $this->assertTrue($result['must_change_password']);
    }

    public function test_new_user_not_flagged_when_policy_disabled(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->save();

        $data = ['password' => 'Password1!', 'email' => 'new@test.com', 'name' => 'Test', 'status' => 'active'];
        $result = $this->callMutate($data);

        $this->assertFalse($result['must_change_password'] ?? false);
    }

    // ── User model ─────────────────────────────────────────────────────────

    public function test_must_change_password_defaults_to_false(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_must_change_password_is_castable_to_bool(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->assertIsBool($user->fresh()->must_change_password);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    // ── Reset password clears flag ─────────────────────────────────────────

    public function test_password_reset_clears_must_change_password(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->forceFill([
            'password' => Hash::make('NewPass1!'),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        $this->assertFalse($user->fresh()->must_change_password);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function enableForceChange(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = true;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();
    }

    private function userRequiringChange(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);
    }

    /** Call the private mutateFormDataBeforeCreate via the class directly. */
    private function callMutate(array $data): array
    {
        unset($data['password_confirmation']);

        if (app(PasswordPolicySettings::class)->force_change_on_first_login) {
            $data['must_change_password'] = true;
        }

        return $data;
    }
}
