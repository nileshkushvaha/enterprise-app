<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\UserRegistered;
use App\Models\User;
use App\Notifications\Auth\RegistrationPendingNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\Auth\WelcomeNotification;
use App\Settings\RegistrationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

final class SendRegistrationNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $settings = app(RegistrationSettings::class);

        // ── Activity: user registered ────────────────────────────────────────
        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $event->ipAddress])
            ->log('User registered');

        // ── Branch: pending admin approval ───────────────────────────────────
        if ($settings->require_admin_approval) {
            // Tell the registrant their account is pending review
            $user->notify(new RegistrationPendingNotification);

            // Log the event — ActivityObserver fires ActivityCreated which routes
            // through NotificationMapper → AdminNotificationService → bell notification
            // for every super_admin. Do NOT send AdminNewRegistrationNotification here
            // directly; that would create a duplicate notification for the same event.
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('registration_pending_approval')
                ->withProperties(['ip' => $event->ipAddress])
                ->log('New registration pending admin approval');

            return;
        }

        // ── Branch: email auto-verified ──────────────────────────────────────
        if ($settings->auto_verify_email) {
            activity('auth')
                ->causedBy($user)
                ->performedOn($user)
                ->event('email_auto_verified')
                ->withProperties(['ip' => $event->ipAddress])
                ->log('Email auto-verified during registration');

            // Send welcome email immediately — Verified event won't fire since
            // we used forceFill rather than markEmailAsVerified()
            if ($settings->send_welcome_email) {
                $user->notify(new WelcomeNotification);

                activity('auth')
                    ->causedBy($user)
                    ->performedOn($user)
                    ->event('welcome_email_queued')
                    ->withProperties(['ip' => $event->ipAddress])
                    ->log('Welcome email queued');
            }

            return;
        }

        // ── Default: send email verification link ────────────────────────────
        $notification = new VerifyEmailNotification;
        $notification->verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
        $user->notify($notification);
    }

    public function failed(UserRegistered $event, Throwable $exception): void
    {
        Log::error('SendRegistrationNotifications failed', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'exception' => $exception->getMessage(),
        ]);
    }
}
