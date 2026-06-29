<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Notifications\Auth\WelcomeNotification;
use App\Settings\RegistrationSettings;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendWelcomeNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function handle(Verified $event): void
    {
        if (! app(RegistrationSettings::class)->send_welcome_email) {
            return;
        }

        $event->user->notify(new WelcomeNotification);
    }

    public function failed(Verified $event, Throwable $exception): void
    {
        Log::error('SendWelcomeNotification failed', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'exception' => $exception->getMessage(),
        ]);
    }
}
