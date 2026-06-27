<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Auth\LoginFailed;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\LogLoginActivity;
use App\Listeners\Auth\SendRegistrationNotifications;
use App\Listeners\Auth\SendWelcomeNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendRegistrationNotifications::class,
        ],
        // Welcome email fires only after the user clicks the verification link
        Verified::class => [
            SendWelcomeNotification::class,
        ],
        UserLoggedIn::class => [
            [LogLoginActivity::class, 'handleUserLoggedIn'],
        ],
        UserLoggedOut::class => [
            [LogLoginActivity::class, 'handleUserLoggedOut'],
        ],
        LoginFailed::class => [
            [LogLoginActivity::class, 'handleLoginFailed'],
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    public function boot(): void
    {
        //
    }
}
