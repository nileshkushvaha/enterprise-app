<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Settings\PasswordPolicySettings;
use Illuminate\Support\Carbon;

final class PasswordLifecycleService
{
    public function __construct(
        private readonly PasswordPolicySettings $settings,
    ) {}

    public function isExpired(User $user): bool
    {
        if (! $this->settings->expiry_enabled || $this->settings->expiry_days <= 0) {
            return false;
        }

        if ($user->password_changed_at === null) {
            return false;
        }

        return Carbon::parse($user->password_changed_at)
            ->addDays($this->settings->expiry_days)
            ->isPast();
    }

    public function expiresAt(User $user): ?Carbon
    {
        if (! $this->settings->expiry_enabled || $user->password_changed_at === null) {
            return null;
        }

        return Carbon::parse($user->password_changed_at)
            ->addDays($this->settings->expiry_days);
    }

    public function expiresIn(User $user): ?int
    {
        $expiresAt = $this->expiresAt($user);

        if ($expiresAt === null) {
            return null;
        }

        return max(0, (int) now()->diffInDays($expiresAt, absolute: false));
    }

    public function passwordExpired(User $user): bool
    {
        return $this->isExpired($user);
    }

    public function passwordExpiresIn(User $user): ?int
    {
        return $this->expiresIn($user);
    }

    public function mustChange(User $user): bool
    {
        if ($this->settings->force_change_on_first_login && $user->must_change_password) {
            return true;
        }

        return $this->isExpired($user);
    }

    public function passwordMustBeChanged(User $user): bool
    {
        return $this->mustChange($user);
    }
}
