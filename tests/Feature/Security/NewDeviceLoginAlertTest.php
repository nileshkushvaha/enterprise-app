<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Notifications\Auth\NewDeviceLoginNotification;
use App\Notifications\Auth\SuspiciousLoginNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * new_device_alerts_enabled — previously stored but never checked anywhere.
 * LoginService now compares the current login's browser/platform against
 * the user's prior successful login_histories rows.
 */
class NewDeviceLoginAlertTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120';

    private const FIREFOX_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0) Firefox/121.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    public function test_first_login_from_a_browser_sends_new_device_notification(): void
    {
        Notification::fake();
        $user = $this->activeUser(['new_device_alerts_enabled' => true, 'login_alerts_enabled' => false]);

        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertSentTo($user, NewDeviceLoginNotification::class);
    }

    public function test_second_login_from_the_same_browser_does_not_resend_new_device_notification(): void
    {
        $user = $this->activeUser(['new_device_alerts_enabled' => true, 'login_alerts_enabled' => false]);

        // First login — establishes the known device (LogLoginActivity is
        // queued but runs via the sync connection in tests).
        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);
        auth()->logout();

        Notification::fake();

        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertNotSentTo($user, NewDeviceLoginNotification::class);
    }

    public function test_login_from_a_different_browser_sends_new_device_notification_again(): void
    {
        $user = $this->activeUser(['new_device_alerts_enabled' => true, 'login_alerts_enabled' => false]);

        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);
        auth()->logout();

        Notification::fake();

        $this->withHeaders(['User-Agent' => self::FIREFOX_WINDOWS])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertSentTo($user, NewDeviceLoginNotification::class);
    }

    public function test_new_device_notification_not_sent_when_disabled(): void
    {
        Notification::fake();
        $user = $this->activeUser(['new_device_alerts_enabled' => false, 'login_alerts_enabled' => false]);

        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertNotSentTo($user, NewDeviceLoginNotification::class);
    }

    public function test_login_alerts_and_new_device_alerts_are_independent_toggles(): void
    {
        Notification::fake();
        $user = $this->activeUser(['login_alerts_enabled' => true, 'new_device_alerts_enabled' => false]);

        $this->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->post(route('auth.login.store'), ['email' => $user->email, 'password' => 'password']);

        Notification::assertSentTo($user, SuspiciousLoginNotification::class);
        Notification::assertNotSentTo($user, NewDeviceLoginNotification::class);
    }

    private function activeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ], $overrides));
    }
}
