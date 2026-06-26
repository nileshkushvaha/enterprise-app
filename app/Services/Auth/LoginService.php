<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Actions\Auth\AttemptLoginAction;
use App\Enums\LoginResult;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\UserLoggedIn;
use App\Models\User;
use App\Notifications\Auth\SuspiciousLoginNotification;
use App\Support\UserAgentParser;

final class LoginService
{
    public function __construct(
        private readonly AttemptLoginAction $attemptLogin,
    ) {}

    public function attempt(
        string  $email,
        string  $password,
        bool    $remember,
        string  $ipAddress,
        string  $userAgent,
    ): LoginResult {
        $user = User::where('email', strtolower($email))->first();

        // Pre-flight checks before touching Auth guard
        if ($user) {
            // Block admin/super_admin from signing in via the frontend portal
            if ($user->hasRole('super_admin') || $user->hasAnyRole(['admin', 'super_admin'])) {
                return LoginResult::AdminAccountOnly;
            }

            if ($user->isLocked()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountLocked->value);
                return LoginResult::AccountLocked;
            }

            if ($user->isBlocked()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountBlocked->value);
                return LoginResult::AccountBlocked;
            }

            if (! $user->isActive()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountInactive->value);
                return LoginResult::AccountInactive;
            }
        }

        // Credential check
        $result = $this->attemptLogin->execute($email, $password, $remember);

        if (! $result->isSuccessful()) {
            LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, $result->value);
            return $result;
        }

        /** @var User $authenticated */
        $authenticated = auth()->user();

        // Check email verification AFTER successful credential check
        if (! $authenticated->hasVerifiedEmail()) {
            auth()->logout();
            LoginFailed::dispatch($authenticated, $email, $ipAddress, $userAgent, LoginResult::EmailUnverified->value);
            return LoginResult::EmailUnverified;
        }

        // ── 2FA check ─────────────────────────────────────────────────
        if ($authenticated->hasTwoFactorEnabled()) {
            // Log the user out — they must pass the 2FA challenge
            auth()->logout();

            session([
                'auth.2fa.user_id' => $authenticated->id,
                'auth.2fa.remember' => $remember,
            ]);

            return LoginResult::RequiresTwoFactor;
        }

        // ── Successful login ──────────────────────────────────────────
        $authenticated->recordSuccessfulLogin($ipAddress, $userAgent);

        // Login alert email (if enabled)
        $this->dispatchLoginAlert($authenticated, $ipAddress, $userAgent);

        UserLoggedIn::dispatch($authenticated, $ipAddress, $userAgent, $remember);

        return LoginResult::Success;
    }

    // ── Private ───────────────────────────────────────────────────────

    private function dispatchLoginAlert(User $user, string $ip, string $ua): void
    {
        if (! $user->login_alerts_enabled) {
            return;
        }

        $parsed  = UserAgentParser::parse($ua);
        $loginAt = now()->format('d M Y, h:i A T');

        $user->notify(new SuspiciousLoginNotification($ip, $parsed['browser'], $parsed['platform'], $loginAt));
    }
}
