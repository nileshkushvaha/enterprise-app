<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminAccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $lockedUser,
        private readonly string $ipAddress,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $appUrl = config('app.url');

        return (new MailMessage)
            ->subject("[{$appName}] Account locked — {$this->lockedUser->email}")
            ->view('emails.auth.admin-account-locked', [
                'admin' => $notifiable,
                'lockedUser' => $this->lockedUser,
                'ipAddress' => $this->ipAddress,
                'appName' => $appName,
                'appUrl' => $appUrl,
            ]);
    }
}
