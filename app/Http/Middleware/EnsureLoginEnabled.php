<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\AuthenticationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureLoginEnabled
{
    public function __construct(
        private readonly AuthenticationSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->login_enabled && $request->isMethod('POST')) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Login is currently disabled. Please try again later.']);
        }

        return $next($request);
    }
}
