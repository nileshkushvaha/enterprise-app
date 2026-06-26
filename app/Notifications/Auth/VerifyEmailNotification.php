<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage(string $url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email Address — ' . config('app.name'))
            ->view('emails.auth.verify-email', [
                'url'      => $url,
                'appName'  => config('app.name'),
                'appUrl'   => config('app.url'),
                'expiry'   => config('auth.verification.expire', 60),
            ]);
    }
}
