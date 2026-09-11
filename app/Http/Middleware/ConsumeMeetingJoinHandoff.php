<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Booking\Services\MeetingJoinHandoffService;
use App\Models\Booking;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for `auth` on the public join route (/join/{booking}); the
 * route deliberately does not also list `auth`, whose priority would
 * otherwise put it first.
 *
 *  1. A request carrying ?handoff=<token> redeems it: the user it was
 *     issued to is signed in on THIS host and redirected to the same
 *     URL without the token, so it never lingers in history or a
 *     referrer. An invalid token is simply dropped.
 *  2. A guest on a host other than APP_URL's is sent to the main host's
 *     handoff endpoint, where their existing session (or the login page,
 *     with intended URL) issues a token and sends them back.
 *  3. A guest on the main host goes to login with this URL as the
 *     intended destination — exactly what `auth` does, never a loop.
 *
 * Nothing here grants access to a meeting: the gateway's participant,
 * lifecycle, status and join-window checks still run afterwards.
 */
final class ConsumeMeetingJoinHandoff
{
    public function __construct(
        private readonly MeetingJoinHandoffService $handoff,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $booking = $this->booking($request);

        if ($booking === null) {
            return $next($request); // the route binding answers 404
        }

        if ($request->query->has(MeetingJoinHandoffService::QUERY_PARAMETER)) {
            return $this->redeem($request, $booking);
        }

        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        return $this->handoff->isMainHost($request)
            ? redirect()->guest(route('auth.login'))
            : redirect()->to($this->handoff->mainHostHandoffUrl($booking));
    }

    private function redeem(Request $request, Booking $booking): Response
    {
        $user = $this->handoff->redeem((string) $request->query(MeetingJoinHandoffService::QUERY_PARAMETER), $booking);

        if ($user !== null) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        // Same URL, token removed — whether or not it was accepted.
        return redirect()->to($request->url());
    }

    /** Route-model binding runs later in the stack, so the parameter may still be the raw id. */
    private function booking(Request $request): ?Booking
    {
        $param = $request->route('booking');

        if ($param instanceof Booking) {
            return $param;
        }

        return is_string($param) ? Booking::query()->find($param) : null;
    }
}
