<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expireMinutes = 60,
    ) {
        $this->queue = 'notifications';
        $this->afterCommit();
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password — '.config('app.name'))
            ->view('emails.auth.password-reset', [
                'url' => $this->resetUrl,
                'expireMinutes' => $this->expireMinutes,
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ]);
    }
}
