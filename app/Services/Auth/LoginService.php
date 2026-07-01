<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Actions\Auth\AttemptLoginAction;
use App\Enums\LoginResult;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\UserLoggedIn;
use App\Models\User;
use App\Notifications\Auth\SuspiciousLoginNotification;
use App\Settings\AuthenticationSettings;
use App\Support\UserAgentParser;

final class LoginService
{
    public function __construct(
        private readonly AttemptLoginAction $attemptLogin,
        private readonly AuthenticationSettings $authSettings,
        private readonly LoginSecurityService $loginSecurity,
        private readonly AccountProtectionService $accountProtection,
    ) {}

    public function attempt(
        string $email,
        string $password,
        bool $remember,
        string $ipAddress,
        string $userAgent,
        ?string $sessionId = null,
        string $loginMethod = 'password',
    ): LoginResult {
        $user = User::where('email', strtolower($email))->first();

        // Pre-flight checks before touching Auth guard
        if ($user) {
            // Auto-unlock: new-style lock (locked_at set) whose duration has expired.
            // This resets the failed-attempt counter so the user gets fresh attempts,
            // and logs the auto-unlock event for the audit trail.
            if ($user->locked_at !== null && ! $user->isLocked()) {
                $this->accountProtection->processAutoUnlock($user, $ipAddress);
                $user->refresh();
            }

            if ($user->isLocked()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountLocked->value, $sessionId);

                return LoginResult::AccountLocked;
            }

            if ($user->isBlocked()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountBlocked->value, $sessionId);

                return LoginResult::AccountBlocked;
            }

            if (! $user->isActive()) {
                LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, LoginResult::AccountInactive->value, $sessionId);

                return LoginResult::AccountInactive;
            }
        }

        // Strip remember flag when the feature is disabled server-side
        if (! $this->authSettings->remember_me_enabled) {
            $remember = false;
        }

        // Credential check (no side-effects inside the action)
        $result = $this->attemptLogin->execute($email, $password, $remember);

        if (! $result->isSuccessful()) {
            // Track the failure (lock / notify / log) for known users
            if ($user) {
                $remaining = $this->loginSecurity->recordFailedAttempt($user, $ipAddress);
                session()->flash('login_remaining_attempts', $remaining);
            }

            LoginFailed::dispatch($user, $email, $ipAddress, $userAgent, $result->value, $sessionId);

            return $result;
        }

        /** @var User $authenticated */
        $authenticated = auth()->user();

        // Check email verification AFTER successful credential check
        if ($this->authSettings->email_verification_required && ! $authenticated->hasVerifiedEmail()) {
            auth()->logout();
            LoginFailed::dispatch($authenticated, $email, $ipAddress, $userAgent, LoginResult::EmailUnverified->value, $sessionId);

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

        UserLoggedIn::dispatch($authenticated, $ipAddress, $userAgent, $remember, $sessionId, $loginMethod);

        return LoginResult::Success;
    }

    // ── Private ───────────────────────────────────────────────────────

    private function dispatchLoginAlert(User $user, string $ip, string $ua): void
    {
        if (! $user->login_alerts_enabled) {
            return;
        }

        $parsed = UserAgentParser::parse($ua);
        $loginAt = now()->format('d M Y, h:i A T');

        $user->notify(new SuspiciousLoginNotification($ip, $parsed['browser'], $parsed['platform'], $loginAt));
    }
}
