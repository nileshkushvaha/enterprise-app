<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Booking\Services\MeetingJoinHandoffService;
use App\Models\Booking;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for `auth` on the public join route (/join/{booking}) and
 * decides WHO is asking to join, without ever signing anyone in:
 *
 *  1. ?handoff=<token> is redeemed exactly once, only on the configured
 *     HTTPS meeting origin, for this booking. Success stores a
 *     booking-scoped JOIN GRANT in this host's session — not a web
 *     login — and the browser is redirected to the same URL without
 *     the token. A token is never applied over a different signed-in
 *     user: an existing identity is kept and the token is discarded.
 *  2. The viewer is the signed-in user (main host), else the grant's
 *     user (meeting host). The controller receives it as a request
 *     attribute and authorizes against it.
 *  3. A guest on the meeting host is sent to the main host's handoff;
 *     a guest on the main host goes to login with the intended URL,
 *     exactly as `auth` does.
 *
 * Nothing here grants access to a meeting: the gateway's participant,
 * account, lifecycle, status and join-window checks still run.
 */
final class ConsumeMeetingJoinHandoff
{
    public const string VIEWER_ATTRIBUTE = 'meeting_join_viewer';

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

        $viewer = Auth::guard('web')->user() ?? $this->handoff->grantedUser($request->session(), $booking);

        if ($viewer instanceof User) {
            $request->attributes->set(self::VIEWER_ATTRIBUTE, $viewer);

            return $next($request);
        }

        return $this->handoff->isMainHost($request)
            ? redirect()->guest(route('auth.login'))
            : redirect()->to($this->handoff->mainHostHandoffUrl($booking));
    }

    private function redeem(Request $request, Booking $booking): RedirectResponse
    {
        $token = (string) $request->query(MeetingJoinHandoffService::QUERY_PARAMETER);
        $user = $this->handoff->redeem($token, $booking, $request);
        $signedIn = Auth::guard('web')->user();

        // Never replace an identity that is already present on this host.
        if ($user !== null && ($signedIn === null || $signedIn->is($user))) {
            $request->session()->regenerate();
            $this->handoff->grant($request->session(), $user, $booking);
        }

        // Same URL, token removed — whether or not it was accepted. The
        // token was in this request's URL, so this response must not be
        // cached and must not leak the URL onward as a referrer.
        return redirect()->to($request->url())
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
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
