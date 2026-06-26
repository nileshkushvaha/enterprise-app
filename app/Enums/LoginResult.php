<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginResult: string
{
    case Success              = 'success';
    case InvalidCredentials   = 'invalid_credentials';
    case AccountLocked        = 'account_locked';
    case AccountBlocked       = 'account_blocked';
    case EmailUnverified      = 'email_unverified';
    case AccountInactive      = 'account_inactive';
    case RequiresTwoFactor    = 'requires_two_factor';
    case AdminAccountOnly     = 'admin_account_only';

    public function message(): string
    {
        return match($this) {
            self::Success              => 'Welcome back!',
            self::InvalidCredentials   => 'These credentials do not match our records.',
            self::AccountLocked        => 'Your account has been temporarily locked due to too many failed attempts. Please check your email to unlock it, or try again in ' . \App\Models\User::LOCK_DURATION_MINUTES . ' minutes.',
            self::AccountBlocked       => 'Your account has been suspended. Please contact support.',
            self::EmailUnverified      => 'Please verify your email address before signing in.',
            self::AccountInactive      => 'Your account is inactive. Please contact support.',
            self::RequiresTwoFactor    => 'Please complete two-factor authentication.',
            self::AdminAccountOnly     => 'These credentials do not match our records.',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Success;
    }
}
