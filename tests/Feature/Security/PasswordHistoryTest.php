<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\UserPasswordHistory;
use App\Services\Auth\PasswordHistoryService;
use App\Services\Profile\ProfileService;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasswordHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    // ── isReused: bypass when disabled ────────────────────────────────

    public function test_reuse_check_skipped_when_prevent_reuse_disabled(): void
    {
        $this->setPolicy(prevent_reuse: false, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        $this->assertFalse(app(PasswordHistoryService::class)->isReused($user, 'OldPass1!'));
    }

    public function test_reuse_check_skipped_when_history_count_is_zero(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 0);
        $user = $this->userWithPassword('OldPass1!');

        $this->assertFalse(app(PasswordHistoryService::class)->isReused($user, 'OldPass1!'));
    }

    // ── isReused: detection ────────────────────────────────────────────

    public function test_reuse_detected_when_password_in_history(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('OldPass1!'),
        ]);

        $this->assertTrue(app(PasswordHistoryService::class)->isReused($user, 'OldPass1!'));
    }

    public function test_new_password_not_flagged_as_reuse(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('OldPass1!'),
        ]);

        $this->assertFalse(app(PasswordHistoryService::class)->isReused($user, 'BrandNew2@'));
    }

    // ── isReused: history count window ────────────────────────────────

    public function test_reuse_not_detected_for_password_outside_history_window(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 2);
        $user = $this->userWithPassword('CurrentPass!');

        // Push 'VeryOldPass1!' beyond the 2-entry window
        UserPasswordHistory::create(['user_id' => $user->id, 'password_hash' => Hash::make('VeryOldPass1!'), 'created_at' => now()->subHours(3)]);
        UserPasswordHistory::create(['user_id' => $user->id, 'password_hash' => Hash::make('RecentPass2!'), 'created_at' => now()->subHours(2)]);
        UserPasswordHistory::create(['user_id' => $user->id, 'password_hash' => Hash::make('CurrentPrev3!'), 'created_at' => now()->subHour()]);

        // With window=2, only the 2 most recent (RecentPass2! and CurrentPrev3!) are checked
        $this->assertFalse(app(PasswordHistoryService::class)->isReused($user, 'VeryOldPass1!'));
        $this->assertTrue(app(PasswordHistoryService::class)->isReused($user, 'RecentPass2!'));
    }

    // ── assertNotReused: throws ValidationException ────────────────────

    public function test_assert_not_reused_throws_when_password_in_history(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('OldPass1!'),
        ]);

        $this->expectException(ValidationException::class);

        app(PasswordHistoryService::class)->assertNotReused($user, 'OldPass1!');
    }

    // ── store: saves hash and prunes ──────────────────────────────────

    public function test_store_saves_old_hash_to_history(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('CurrentPass1!');
        $oldHash = $user->password;

        app(PasswordHistoryService::class)->store($user, $oldHash);

        $this->assertDatabaseHas('user_password_histories', [
            'user_id' => $user->id,
        ]);
    }

    public function test_store_prunes_entries_beyond_history_count(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 3);
        $user = $this->userWithPassword('CurrentPass!');

        // Seed 3 existing entries
        foreach (range(1, 3) as $i) {
            UserPasswordHistory::create([
                'user_id' => $user->id,
                'password_hash' => Hash::make("Pass{$i}!"),
                'created_at' => now()->subHours($i),
            ]);
        }

        // Store one more — should prune the oldest
        app(PasswordHistoryService::class)->store($user, Hash::make('Pass4!'));

        $this->assertSame(3, UserPasswordHistory::where('user_id', $user->id)->count());
    }

    public function test_store_skips_empty_hash(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('CurrentPass1!');

        app(PasswordHistoryService::class)->store($user, '');

        $this->assertDatabaseEmpty('user_password_histories');
    }

    // ── Integration: ProfileService blocks reuse ───────────────────────

    public function test_profile_password_change_blocked_on_reuse(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        // Seed history with old password
        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('OldPass1!'),
        ]);

        $this->expectException(ValidationException::class);

        app(ProfileService::class)->changePassword($user, 'OldPass1!');
    }

    public function test_profile_password_change_stores_old_hash(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);
        $user = $this->userWithPassword('OldPass1!');

        app(ProfileService::class)->changePassword($user, 'NewPass2!');

        $this->assertSame(1, UserPasswordHistory::where('user_id', $user->id)->count());
    }

    // ── Integration: ForcePasswordChange blocks reuse ──────────────────

    public function test_force_change_blocked_when_password_reused(): void
    {
        $this->setPolicy(prevent_reuse: true, count: 5);

        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'must_change_password' => true,
            'password' => Hash::make('OldPass1!'),
        ]);

        $s = app(PasswordPolicySettings::class);
        $s->force_change_on_first_login = true;
        $s->save();

        // Seed current password in history
        UserPasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => Hash::make('OldPass1!'),
        ]);

        $this->actingAs($user)
            ->post(route('auth.password.change-required.store'), [
                'password' => 'OldPass1!',
                'password_confirmation' => 'OldPass1!',
            ])
            ->assertSessionHasErrors('password');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function setPolicy(bool $prevent_reuse, int $count): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->prevent_reuse = $prevent_reuse;
        $s->password_history_count = $count;
        $s->min_length = 8;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();
    }

    private function userWithPassword(string $plainPassword): User
    {
        return User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make($plainPassword),
        ]);
    }
}
