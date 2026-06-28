<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminSessionService
{
    public function forceLogoutAllDevices(): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->truncate();
        }

        User::query()->update(['remember_token' => null]);

        activity('security')
            ->causedBy(auth()->user())
            ->event('force_logout_all')
            ->log('Force logout all devices executed');
    }
}
