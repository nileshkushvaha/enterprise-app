<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PortalResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps Admin Portal users (super_admin, manager) off the Frontend Portal —
 * they belong in Filament. Delegates the actual portal rule to
 * PortalResolver; this middleware only wires it into the request lifecycle.
 */
class EnsureFrontendPortal
{
    public function __construct(private readonly PortalResolver $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $this->portal->usesAdminPortal($user)) {
            return redirect($this->portal->homeRoute($user));
        }

        return $next($request);
    }
}
