<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Booking\Services\MeetingJoinHandoffService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Main-host half of the cross-host sign-in for the participant join
 * gateway (see MeetingJoinHandoffService). Behind the dashboard
 * middleware, so a guest lands on login with this URL as the intended
 * destination and comes back here after signing in. Only a participant
 * of the booking is issued a token; the gateway on the join host then
 * applies every remaining rule.
 */
final class MeetingJoinHandoffController extends Controller
{
    public function __invoke(Booking $booking, MeetingJoinHandoffService $handoff, AuditTrailService $audit): RedirectResponse
    {
        Gate::authorize('view', $booking);

        /** @var User $user */
        $user = auth()->user();

        $token = $handoff->issue($user, $booking);

        // Who was handed to the join host for which lesson — never the token.
        $audit->logUser($user, 'bookings', 'meeting_join_handoff_issued', sprintf('Participant handed to the join host for booking %s.', $booking->reference), $booking, [
            'ttl_seconds' => MeetingJoinHandoffService::TTL_SECONDS,
        ]);

        return redirect()->away($handoff->joinUrlWithToken($booking, $token));
    }
}
