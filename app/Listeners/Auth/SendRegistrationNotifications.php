<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\UserRegistered;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\Auth\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendRegistrationNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        // Verification email (replaces default Laravel notification)
        $user->notify(new VerifyEmailNotification);

        // Welcome email
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
}
