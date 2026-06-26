<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Notifications\Auth\PasswordResetNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

final class PasswordResetService
{
    /**
     * Generate + store a reset token, then send the notification.
     * Always returns 'success' to the controller to prevent email enumeration.
     */
    public function sendResetLink(string $email): void
    {
        $user = User::where('email', strtolower($email))->first();

        // If no user, silently do nothing — we show a success message anyway
        if (! $user) {
            return;
        }

        // Generate token via Laravel's built-in broker
        $token = Password::createToken($user);

        // Build the reset URL pointing to our custom page
        $resetUrl = url(route('auth.password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], absolute: false));

        $expireMinutes = config('auth.passwords.users.expire', 60);

        // Dispatch queued notification
        $user->notify(new PasswordResetNotification($resetUrl, $expireMinutes));

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => request()->ip()])
            ->log('Password reset link requested');
    }

    /**
     * Validate token, reset password, queue changed notification.
     * Returns null on success, error string on failure.
     */
    public function resetPassword(
        string $email,
        string $token,
        string $password,
        string $ipAddress,
    ): ?string {
        $status = Password::reset(
            [
                'email'                 => strtolower($email),
                'password'              => $password,
                'password_confirmation' => $password,
                'token'                 => $token,
            ],
            function (User $user, string $password) use ($ipAddress): void {
                $user->forceFill([
                    'password'            => Hash::make($password),
                    'password_changed_at' => now(),
                ])->save();

                // Send "your password was changed" alert email
                $user->notify(new PasswordChangedNotification(
                    ipAddress: $ipAddress,
                    changedAt: Carbon::now()->toDateTimeString(),
                ));

                activity('auth')
                    ->causedBy($user)
                    ->performedOn($user)
                    ->withProperties(['ip' => $ipAddress])
                    ->log('Password reset via reset link');
            }
        );

        return $status === Password::PASSWORD_RESET ? null : __($status);
    }
}
