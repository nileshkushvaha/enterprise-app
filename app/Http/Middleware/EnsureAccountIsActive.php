<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isLocked()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('auth.login')
                ->withErrors(['email' => 'Your account is temporarily locked. Please try again later or reset your password.']);
        }

        if ($user->isBlocked()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('auth.login')
                ->withErrors(['email' => 'Your account has been suspended. Please contact support.']);
        }

        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('auth.login')
                ->withErrors(['email' => 'Your account is inactive. Please contact support.']);
        }

        return $next($request);
    }
}
