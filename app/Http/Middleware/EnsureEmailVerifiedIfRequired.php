<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\AuthenticationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailVerifiedIfRequired
{
    public function __construct(
        private readonly AuthenticationSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->email_verification_required) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your email address is not verified.'], 403);
            }

            return redirect()->route('auth.verification.notice');
        }

        return $next($request);
    }
}
