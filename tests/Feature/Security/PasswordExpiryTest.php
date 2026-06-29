<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Auth\PasswordLifecycleService;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    // ── PasswordLifecycleService: isExpired ────────────────────────────

    public function test_is_expired_returns_false_when_expiry_disabled(): void
    {
        $this->enableExpiry(enabled: false);
        $user = $this->userWithChangedAt(daysAgo: 200);

        $this->assertFalse(app(PasswordLifecycleService::class)->isExpired($user));
    }

    public function test_is_expired_returns_false_when_password_changed_at_null(): void
    {
        $this->enableExpiry(enabled: true, days: 90);

        $user = User::factory()->create([
            'status' => 'active',
            'password_changed_at' => null,
        ]);

        $this->assertFalse(app(PasswordLifecycleService::class)->isExpired($user));
    }

    public function test_is_expired_returns_true_when_past_expiry(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $user = $this->userWithChangedAt(daysAgo: 31);

        $this->assertTrue(app(PasswordLifecycleService::class)->isExpired($user));
    }

    public function test_is_expired_returns_false_when_within_expiry_window(): void
    {
        $this->enableExpiry(enabled: true, days: 90);
        $user = $this->userWithChangedAt(daysAgo: 45);

        $this->assertFalse(app(PasswordLifecycleService::class)->isExpired($user));
    }

    // ── PasswordLifecycleService: expiresIn ───────────────────────────

    public function test_expires_in_returns_null_when_expiry_disabled(): void
    {
        $this->enableExpiry(enabled: false);
        $user = $this->userWithChangedAt(daysAgo: 10);

        $this->assertNull(app(PasswordLifecycleService::class)->expiresIn($user));
    }

    public function test_expires_in_returns_remaining_days(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $user = $this->userWithChangedAt(daysAgo: 20);

        $remaining = app(PasswordLifecycleService::class)->expiresIn($user);

        // Should be around 10 days (allow ±1 for test timing)
        $this->assertGreaterThanOrEqual(9, $remaining);
        $this->assertLessThanOrEqual(11, $remaining);
    }

    public function test_expires_in_returns_zero_when_already_expired(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $user = $this->userWithChangedAt(daysAgo: 60);

        $this->assertSame(0, app(PasswordLifecycleService::class)->expiresIn($user));
    }

    // ── PasswordLifecycleService: mustChange ──────────────────────────

    public function test_must_change_returns_true_for_flagged_user_when_policy_enabled(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = true;
        $s->expiry_enabled = false;
        $s->save();

        $user = User::factory()->create(['must_change_password' => true]);

        $this->assertTrue(app(PasswordLifecycleService::class)->mustChange($user));
    }

    public function test_must_change_returns_false_for_flagged_user_when_policy_disabled(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->expiry_enabled = false;
        $s->save();

        $user = User::factory()->create(['must_change_password' => true]);

        $this->assertFalse(app(PasswordLifecycleService::class)->mustChange($user));
    }

    public function test_must_change_returns_true_when_password_expired(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->save();

        $user = $this->userWithChangedAt(daysAgo: 45);

        $this->assertTrue(app(PasswordLifecycleService::class)->mustChange($user));
    }

    public function test_must_change_returns_false_when_no_conditions_apply(): void
    {
        $this->enableExpiry(enabled: false);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->save();

        $user = User::factory()->create(['must_change_password' => false, 'password_changed_at' => now()]);

        $this->assertFalse(app(PasswordLifecycleService::class)->mustChange($user));
    }

    // ── Middleware: expiry triggers redirect ───────────────────────────

    public function test_expired_password_redirects_to_force_change(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $user = $this->userWithChangedAt(daysAgo: 45);
        $user->update(['email_verified_at' => now(), 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('auth.password.change-required'));
    }

    public function test_non_expired_password_does_not_redirect(): void
    {
        $this->enableExpiry(enabled: true, days: 90);

        $user = $this->userWithChangedAt(daysAgo: 30);
        $user->update(['email_verified_at' => now(), 'status' => 'active', 'must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_expiry_disabled_no_redirect_even_for_old_password(): void
    {
        $this->enableExpiry(enabled: false);

        $user = $this->userWithChangedAt(daysAgo: 500);
        $user->update(['email_verified_at' => now(), 'status' => 'active', 'must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // ── Force change page: accessible when password expired ────────────

    public function test_force_change_page_accessible_when_password_expired(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $user = $this->userWithChangedAt(daysAgo: 60);
        $user->update(['email_verified_at' => now(), 'status' => 'active', 'must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('auth.password.change-required'))
            ->assertOk();
    }

    public function test_expired_user_can_change_password_via_force_change(): void
    {
        $this->enableExpiry(enabled: true, days: 30);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = false;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $user = $this->userWithChangedAt(daysAgo: 60);
        $user->update([
            'email_verified_at' => now(),
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'NewStrongPass1!',
                'password_confirmation' => 'NewStrongPass1!',
            ])
            ->assertRedirect(route('dashboard'));

        // password_changed_at should now be refreshed
        $this->assertFalse(
            app(PasswordLifecycleService::class)->isExpired($user->fresh())
        );
    }

    // ── Middleware priority: must_change takes precedence ──────────────

    public function test_must_change_flag_takes_priority_over_expiry(): void
    {
        // Both conditions active
        $this->enableExpiry(enabled: true, days: 30);
        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = true;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $user = $this->userWithChangedAt(daysAgo: 60);
        $user->update([
            'email_verified_at' => now(),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        // mustChange() should still return true — both conditions met
        $this->assertTrue(app(PasswordLifecycleService::class)->mustChange($user));
    }

    // ── Expiry resets after password change ────────────────────────────

    public function test_expiry_check_passes_after_password_change(): void
    {
        $this->enableExpiry(enabled: true, days: 30);

        $user = $this->userWithChangedAt(daysAgo: 60);
        // Now reset password_changed_at to now
        $user->update(['password_changed_at' => now()]);

        $this->assertFalse(app(PasswordLifecycleService::class)->isExpired($user->fresh()));
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function enableExpiry(bool $enabled, int $days = 90): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->expiry_enabled = $enabled;
        $s->expiry_days = $days;
        $s->save();
    }

    private function userWithChangedAt(int $daysAgo): User
    {
        return User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('Password1!'),
            'password_changed_at' => now()->subDays($daysAgo),
            'must_change_password' => false,
        ]);
    }
}
