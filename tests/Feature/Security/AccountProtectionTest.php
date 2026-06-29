<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Notifications\Auth\AccountLockedNotification;
use App\Notifications\Auth\AccountUnlockedNotification;
use App\Notifications\Auth\AdminAccountLockedNotification;
use App\Services\Auth\AccountProtectionService;
use App\Settings\AccountProtectionSettings;
use App\Settings\LoginSecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
        $this->enableLocking();
    }

    // ── disable_after_failed_attempts: master switch ──────────────────

    public function test_account_locked_when_protection_enabled(): void
    {
        $this->setLockout(max: 2);

        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertTrue($user->fresh()->isLocked());
    }

    public function test_account_not_locked_when_protection_disabled(): void
    {
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->disable_after_failed_attempts = false;
        $ap->save();

        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertFalse($user->fresh()->isLocked());
    }

    public function test_lock_sets_locked_at_timestamp(): void
    {
        $this->setLockout(max: 2);
        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertNotNull($user->fresh()->locked_at);
    }

    public function test_lock_sets_lock_reason_failed_attempts(): void
    {
        $this->setLockout(max: 2);
        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertSame('failed_attempts', $user->fresh()->lock_reason);
    }

    // ── auto_unlock_after: lock duration ─────────────────────────────

    public function test_locked_until_set_when_auto_unlock_after_greater_than_zero(): void
    {
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->auto_unlock_after = 45;
        $ap->save();

        $user = $this->activeUser(['failed_login_count' => 1]);
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->locked_until);
        $this->assertTrue($fresh->locked_until->isFuture());
    }

    public function test_locked_until_null_when_auto_unlock_after_is_zero(): void
    {
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->auto_unlock_after = 0;
        $ap->save();

        $user = $this->activeUser(['failed_login_count' => 1]);
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $fresh = $user->fresh();
        $this->assertNull($fresh->locked_until);
        $this->assertTrue($fresh->isLocked()); // locked_at set + no expiry = still locked
    }

    public function test_manual_lock_only_account_cannot_login(): void
    {
        // locked_at set, locked_until null = manual unlock required
        $user = $this->activeUser(['locked_at' => now(), 'locked_until' => null, 'lock_reason' => 'manual_admin']);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ── Auto unlock on login ──────────────────────────────────────────

    public function test_auto_unlock_processes_when_lock_expires_at_login(): void
    {
        $this->setLockout(max: 2);

        // Account was locked (new-style: locked_at set) but lock has now expired
        $user = $this->activeUser([
            'failed_login_count' => 2,
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
            'lock_reason' => 'failed_attempts',
        ]);

        // Successful login after expiry should clear lock fields
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $fresh = $user->fresh();
        $this->assertNull($fresh->locked_at);
        $this->assertNull($fresh->locked_until);
        $this->assertNull($fresh->lock_reason);
        $this->assertSame(0, $fresh->failed_login_count);
    }

    public function test_auto_unlock_activity_logged(): void
    {
        $this->setLockout(max: 2);

        $user = $this->activeUser([
            'failed_login_count' => 2,
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
            'lock_reason' => 'failed_attempts',
        ]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'auto_unlock',
            'subject_id' => $user->id,
        ]);
    }

    public function test_auto_unlock_resets_failed_login_count(): void
    {
        $this->setLockout(max: 2);

        $user = $this->activeUser([
            'failed_login_count' => 2,
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
            'lock_reason' => 'failed_attempts',
        ]);

        // Trigger auto-unlock via a login attempt (even wrong password processes auto-unlock first)
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        // After unlock, count is reset
        $this->assertSame(0, $user->fresh()->failed_login_count);
    }

    public function test_legacy_lock_without_locked_at_still_expires_normally(): void
    {
        // Legacy lock: only locked_until set (pre-migration records)
        $user = $this->activeUser([
            'failed_login_count' => 3,
            'locked_until' => now()->subMinute(), // expired
        ]);

        // Should NOT trigger auto-unlock (no locked_at = not new-style)
        $this->assertFalse($user->isLocked());

        // Can login normally
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    // ── User notifications ────────────────────────────────────────────

    public function test_user_notified_on_lock_when_notify_user_enabled(): void
    {
        Notification::fake();
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = true;
        $ap->save();

        $user = $this->activeUser(['failed_login_count' => 1]);
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        Notification::assertSentTo($user, AccountLockedNotification::class);
    }

    public function test_user_not_notified_on_lock_when_notify_user_disabled(): void
    {
        Notification::fake();
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = false;
        $ap->save();

        $user = $this->activeUser(['failed_login_count' => 1]);
        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        Notification::assertNotSentTo($user, AccountLockedNotification::class);
    }

    public function test_user_notified_on_auto_unlock_when_notify_user_enabled(): void
    {
        Notification::fake();
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = true;
        $ap->save();

        $user = $this->activeUser([
            'failed_login_count' => 2,
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
            'lock_reason' => 'failed_attempts',
        ]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertSentTo($user, AccountUnlockedNotification::class);
    }

    public function test_user_not_notified_on_auto_unlock_when_notify_user_disabled(): void
    {
        Notification::fake();
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = false;
        $ap->save();

        $user = $this->activeUser([
            'failed_login_count' => 2,
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
            'lock_reason' => 'failed_attempts',
        ]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertNotSentTo($user, AccountUnlockedNotification::class);
    }

    // ── Admin notifications ───────────────────────────────────────────

    public function test_admin_notified_on_lock_when_notify_admin_enabled(): void
    {
        Notification::fake();
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_admin = true;
        $ap->save();

        $superAdmin = $this->createSuperAdmin();
        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        Notification::assertSentTo($superAdmin, AdminAccountLockedNotification::class);
    }

    public function test_admin_not_notified_when_notify_admin_disabled(): void
    {
        Notification::fake();
        $this->setLockout(max: 2);
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_admin = false;
        $ap->save();

        $superAdmin = $this->createSuperAdmin();
        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        Notification::assertNotSentTo($superAdmin, AdminAccountLockedNotification::class);
    }

    // ── Activity logging ──────────────────────────────────────────────

    public function test_account_locked_event_logged(): void
    {
        $this->setLockout(max: 2);
        $user = $this->activeUser(['failed_login_count' => 1]);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'wrong']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'account_locked',
            'subject_id' => $user->id,
        ]);
    }

    public function test_manual_unlock_activity_logged(): void
    {
        $user = $this->activeUser([
            'locked_at' => now(),
            'locked_until' => now()->addMinutes(30),
            'lock_reason' => 'failed_attempts',
        ]);

        app(AccountProtectionService::class)->manualUnlock($user, method: 'admin');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'manual_unlock',
            'subject_id' => $user->id,
        ]);
    }

    public function test_self_service_unlock_activity_logged(): void
    {
        $user = $this->activeUser([
            'locked_at' => now(),
            'locked_until' => now()->addMinutes(30),
            'lock_reason' => 'failed_attempts',
        ]);

        app(AccountProtectionService::class)->manualUnlock($user, actor: $user, method: 'self_service');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'self_service_unlock',
            'subject_id' => $user->id,
        ]);
    }

    public function test_manual_lock_activity_logged(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $user = $this->activeUser();

        app(AccountProtectionService::class)->manualLock($user, actor: $superAdmin);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'manual_lock',
            'subject_id' => $user->id,
        ]);
    }

    // ── AccountProtectionService: manual lock/unlock ──────────────────

    public function test_manual_lock_sets_locked_at_and_reason(): void
    {
        $user = $this->activeUser();
        $superAdmin = $this->createSuperAdmin();

        app(AccountProtectionService::class)->manualLock($user, actor: $superAdmin);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->locked_at);
        $this->assertNull($fresh->locked_until);   // no auto-expiry on manual lock
        $this->assertSame('manual_admin', $fresh->lock_reason);
        $this->assertTrue($fresh->isManualLock());
        $this->assertTrue($fresh->isLocked());
    }

    public function test_manual_unlock_clears_all_lock_fields(): void
    {
        $user = $this->activeUser([
            'locked_at' => now(),
            'locked_until' => null,
            'lock_reason' => 'manual_admin',
            'failed_login_count' => 3,
        ]);

        app(AccountProtectionService::class)->manualUnlock($user);

        $fresh = $user->fresh();
        $this->assertNull($fresh->locked_at);
        $this->assertNull($fresh->locked_until);
        $this->assertNull($fresh->lock_reason);
        $this->assertSame(0, $fresh->failed_login_count);
        $this->assertFalse($fresh->isLocked());
    }

    public function test_manually_locked_user_cannot_login(): void
    {
        $user = $this->activeUser();
        $superAdmin = $this->createSuperAdmin();

        app(AccountProtectionService::class)->manualLock($user, actor: $superAdmin);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unlocked_user_can_login_after_manual_unlock(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $user = $this->activeUser([
            'locked_at' => now(),
            'locked_until' => null,
            'lock_reason' => 'manual_admin',
        ]);

        app(AccountProtectionService::class)->manualUnlock($user, actor: $superAdmin);

        $this->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    // ── User notification on manual unlock ────────────────────────────

    public function test_user_notified_on_manual_unlock(): void
    {
        Notification::fake();
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = true;
        $ap->save();

        $user = $this->activeUser(['locked_at' => now(), 'locked_until' => null]);
        app(AccountProtectionService::class)->manualUnlock($user);

        Notification::assertSentTo($user, AccountUnlockedNotification::class);
    }

    public function test_user_not_notified_on_manual_unlock_when_disabled(): void
    {
        Notification::fake();
        $ap = app(AccountProtectionSettings::class);
        $ap->notify_user = false;
        $ap->save();

        $user = $this->activeUser(['locked_at' => now(), 'locked_until' => null]);
        app(AccountProtectionService::class)->manualUnlock($user);

        Notification::assertNotSentTo($user, AccountUnlockedNotification::class);
    }

    // ── isLocked() edge cases ─────────────────────────────────────────

    public function test_is_locked_false_when_no_lock_fields_set(): void
    {
        $user = $this->activeUser();
        $this->assertFalse($user->isLocked());
    }

    public function test_is_locked_true_when_locked_at_set_and_no_expiry(): void
    {
        $user = $this->activeUser(['locked_at' => now(), 'locked_until' => null]);
        $this->assertTrue($user->isLocked());
        $this->assertTrue($user->isManualLock());
    }

    public function test_is_locked_true_when_locked_until_in_future(): void
    {
        $user = $this->activeUser([
            'locked_at' => now(),
            'locked_until' => now()->addMinutes(30),
        ]);
        $this->assertTrue($user->isLocked());
        $this->assertFalse($user->isManualLock());
    }

    public function test_is_locked_false_when_locked_until_in_past(): void
    {
        $user = $this->activeUser([
            'locked_at' => now()->subHour(),
            'locked_until' => now()->subMinute(),
        ]);
        $this->assertFalse($user->isLocked());
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function enableLocking(): void
    {
        $ap = app(AccountProtectionSettings::class);
        $ap->disable_after_failed_attempts = true;
        $ap->auto_unlock_after = 30;
        $ap->notify_user = true;
        $ap->notify_admin = false;
        $ap->save();
    }

    private function setLockout(int $max = 3, int $duration = 15): void
    {
        $s = app(LoginSecuritySettings::class);
        $s->max_failed_attempts = $max;
        $s->lockout_duration = $duration;
        $s->save();
    }

    private function activeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ], $overrides));
    }

    private function createSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($role);

        return $admin;
    }
}
