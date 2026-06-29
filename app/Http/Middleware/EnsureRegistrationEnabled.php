<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\RegistrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRegistrationEnabled
{
    public function __construct(
        private readonly RegistrationSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->self_registration_enabled) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('auth.login')
                ->with('error', 'Online registration is currently unavailable.');
        }

        abort(403, 'Online registration is currently unavailable.');
    }
}
