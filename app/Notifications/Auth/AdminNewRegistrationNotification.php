<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminNewRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $registeredUser,
        private readonly string $ipAddress,
    ) {
        $this->onQueue('notifications')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New registration pending approval — '.config('app.name'))
            ->view('emails.auth.admin-new-registration', [
                'admin' => $notifiable,
                'registeredUser' => $this->registeredUser,
                'ipAddress' => $this->ipAddress,
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ]);
    }
}
