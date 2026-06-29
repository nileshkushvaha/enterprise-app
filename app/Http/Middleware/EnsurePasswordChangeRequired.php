<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PasswordLifecycleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChangeRequired
{
    public function __construct(
        private readonly PasswordLifecycleService $lifecycle,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->lifecycle->mustChange($user)) {
            return $next($request);
        }

        $isAdminContext = $request->is('admin') || $request->is('admin/*');

        if ($isAdminContext) {
            // Allow the admin change-password page and logout through
            if ($request->is('admin/change-password') || $request->routeIs('filament.admin.auth.logout')) {
                return $next($request);
            }

            return redirect('/admin/change-password')
                ->with('warning', 'You must change your password before continuing.');
        }

        // Allow the force-change page and logout through
        if ($request->routeIs('auth.password.change-required', 'auth.password.change-required.store', 'auth.logout')) {
            return $next($request);
        }

        return redirect()->route('auth.password.change-required')
            ->with('warning', 'You must change your password before you can continue.');
    }
}
