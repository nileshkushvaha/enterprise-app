<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\UserRegistered;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\Auth\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendRegistrationNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    /** Retry up to 3 times before marking as failed */
    public int $tries = 3;

    /** Wait 30s, 60s, 120s between retries */
    public array $backoff = [30, 60, 120];

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        // Send verification email (queued notification)
        $notification = new VerifyEmailNotification;
        $notification->verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'auth.verification.verify',
            \Illuminate\Support\Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
        $user->notify($notification);

        // Send welcome email
        $user->notify(new WelcomeNotification);

        // Activity log
        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties([
                'ip'         => $event->ipAddress,
                'user_agent' => $event->userAgent,
            ])
            ->log('User registered');
    }

    public function failed(UserRegistered $event, Throwable $exception): void
    {
        Log::error('SendRegistrationNotifications failed', [
            'user_id'   => $event->user->id,
            'email'     => $event->user->email,
            'exception' => $exception->getMessage(),
        ]);
    }
}
