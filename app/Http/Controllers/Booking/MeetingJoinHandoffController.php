<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Services\MeetingJoinHandoffService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Main-host half of the cross-host authorization for the participant
 * join gateway (see MeetingJoinHandoffService). Behind the dashboard
 * middleware, so a guest lands on login with this URL as the intended
 * destination and comes back here after signing in. Only served on the
 * main host, only to a participant of the booking, and only when a
 * meeting origin is configured — otherwise the participant is simply
 * sent to the main-host join link their session already covers.
 */
final class MeetingJoinHandoffController extends Controller
{
    public function __invoke(Booking $booking, Request $request, MeetingJoinHandoffService $handoff, BookingMeetingServiceInterface $meetings, AuditTrailService $audit): RedirectResponse
    {
        abort_unless($handoff->isMainHost($request), 404);

        Gate::authorize('view', $booking);

        /** @var User $user */
        $user = auth()->user();

        $token = $handoff->issue($user, $booking);

        if ($token === null) {
            return redirect()->to($meetings->joinLinkFor($booking));
        }

        // Who was handed to the meeting host for which lesson — never the token.
        $audit->logUser($user, 'bookings', 'meeting_join_handoff_issued', sprintf('Participant handed to the meeting host for booking %s.', $booking->reference), $booking, [
            'ttl_seconds' => MeetingJoinHandoffService::TOKEN_TTL_SECONDS,
        ]);

        // The Location carries the token: never cache it, never send it on as a referrer.
        return redirect()->away($handoff->joinUrlWithToken($booking, $token))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
