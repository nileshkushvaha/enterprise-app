<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\Auth\UserLoggedOut;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $ipAddress = $request->ip() ?? '127.0.0.1';
        $userAgent = $request->userAgent() ?? '';

        if ($user) {
            UserLoggedOut::dispatch($user, $ipAddress, $userAgent);
        }

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
