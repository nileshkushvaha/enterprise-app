<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Support\UserAgentParser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->hasSession()) {
            $sessionId = $request->session()->getId();
            $ua = $request->userAgent() ?? '';
            $parsed = UserAgentParser::parse($ua);

            UserSession::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_id' => $request->user()->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($ua, 0, 500),
                    'browser' => $parsed['browser'],
                    'platform' => $parsed['platform'],
                    'device_type' => $parsed['device_type'],
                    'last_activity_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return $response;
    }
}
