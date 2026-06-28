<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\LoginFailed;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Models\LoginHistory;
use App\Support\UserAgentParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogLoginActivity implements ShouldQueue
{
    public string $queue   = 'default';
    public int    $tries   = 3;
    public array  $backoff = [30, 60, 120];

    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $ua = UserAgentParser::parse($event->userAgent);

        LoginHistory::create([
            'user_id'      => $event->user->id,
            'status'       => 'success',
            'ip_address'   => $event->ipAddress,
            'user_agent'   => mb_substr($event->userAgent, 0, 500),
            'browser'      => $ua['browser'],
            'platform'     => $ua['platform'],
            'device_type'  => $ua['device_type'],
            'logged_in_at' => now(),
        ]);

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => $event->ipAddress, 'remember' => $event->remember])
            ->log('User logged in');
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        LoginHistory::where('user_id', $event->user->id)
            ->whereNull('logged_out_at')
            ->latest('logged_in_at')
            ->first()
            ?->update(['logged_out_at' => now()]);

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => $event->ipAddress])
            ->log('User logged out');
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        $ua = UserAgentParser::parse($event->userAgent);

        $data = [
            'status'       => 'failed',
            'ip_address'   => $event->ipAddress,
            'user_agent'   => mb_substr($event->userAgent, 0, 500),
            'browser'      => $ua['browser'],
            'platform'     => $ua['platform'],
            'device_type'  => $ua['device_type'],
            'logged_in_at' => now(),
        ];

        if ($event->user) {
            $data['user_id'] = $event->user->id;
            $data['status']  = $event->reason;

            activity('auth')
                ->causedBy($event->user)
                ->withProperties(['ip' => $event->ipAddress, 'reason' => $event->reason])
                ->log('Login attempt failed: ' . $event->reason);
        }

        LoginHistory::create($data);
    }

    public function failed(mixed $event, Throwable $exception): void
    {
        Log::error('LogLoginActivity failed permanently after all retries', [
            'event'     => get_class($event),
            'exception' => $exception->getMessage(),
        ]);
    }
}
