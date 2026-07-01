<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PortalResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reserved for future plain Laravel routes registered directly under
 * /admin/* outside Filament's own panel routing. Filament's panel already
 * fully protects its own routes via User::canAccessPanel() + authMiddleware
 * — do not apply this middleware to the panel itself, since that would
 * block guests from reaching /admin/login (the panel must stay reachable
 * in order to authenticate against it).
 */
class EnsureAdminPortal
{
    public function __construct(private readonly PortalResolver $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $this->portal->usesAdminPortal($user)) {
            abort(403);
        }

        return $next($request);
    }
}
