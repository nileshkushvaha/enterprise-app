<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

final class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Pre-generate the signed URL at dispatch time so the URL remains valid
     * even if the queue worker runs much later and the request context is gone.
     */
    public string $verificationUrl;

    public function __construct()
    {
        $this->queue = 'notifications';
        $this->afterCommit();  // Fire after DB transaction commits
    }

    /**
     * Override to use pre-generated URL if available.
     */
    protected function verificationUrl(mixed $notifiable): string
    {
        if (isset($this->verificationUrl)) {
            return $this->verificationUrl;
        }

        return URL::temporarySignedRoute(
            'auth.verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    protected function buildMailMessage($url): MailMessage
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

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address — ' . config('app.name'))
            ->view('emails.auth.verify-email', [
                'url'        => $url,
                'notifiable' => $notifiable,
                'appName'    => config('app.name'),
                'appUrl'     => config('app.url'),
                'expiry'     => config('auth.verification.expire', 60),
            ]);
    }
}
