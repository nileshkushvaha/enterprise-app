<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConsumeMeetingJoinHandoff;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The SIRI meeting join gateway.
 *
 * Every participant-facing page, API payload and notification carries
 * the SIRI join link (the dashboard route, or `/join/{booking}` on the
 * configured meeting host) instead of the provider's URL.
 * Following it re-runs the whole authorization on the server, at click
 * time: signed in or holding a booking-scoped join grant, account
 * active, a participant of THIS booking (BookingPolicy::view +
 * participantJoinUrlFor), the
 * student's or instructor's lifecycle, the role visibility setting,
 * booking confirmed, meeting created, and the configured join window.
 * Only then is the participant redirected to the provider's join URL.
 *
 * What it never does: hand out a Zoom host start URL, a credential, or
 * anything provider-side beyond the participant redirect. It is an
 * authenticated entry point on SIRI's own domain — not a custom Zoom
 * domain, and not a proxy of the meeting itself.
 */
final class MeetingJoinController extends Controller
{
    public function __invoke(Booking $booking, Request $request, BookingMeetingServiceInterface $meetings, AuditTrailService $audit): RedirectResponse|View
    {
        // The signed-in user on the main host, or the booking-scoped join
        // grant's user on the meeting host (ConsumeMeetingJoinHandoff);
        // the dashboard route only ever has the former.
        $viewer = $request->attributes->get(ConsumeMeetingJoinHandoff::VIEWER_ATTRIBUTE) ?? auth()->user();

        abort_unless($viewer instanceof User, 401);

        // Strangers get 403 before anything about the lesson is rendered.
        Gate::forUser($viewer)->authorize('view', $booking);

        $url = $meetings->participantJoinUrlFor($booking, $viewer);

        if ($url === null) {
            return view('dashboard.meetings.join-unavailable', [
                'booking' => $booking->loadMissing('type'),
                'availability' => $meetings->participantJoinAvailabilityFor($booking, $viewer),
                'isParticipant' => $viewer->id === $booking->student_id || $viewer->id === $booking->instructor_id,
                'viewerTimezone' => $viewer->profile?->timezone,
            ]);
        }

        // Who joined which lesson through which provider — never the URL.
        $audit->logUser($viewer, 'bookings', 'meeting_join_redirected', sprintf('Participant joined the meeting for booking %s.', $booking->reference), $booking, [
            'provider' => $booking->meeting?->provider,
            'role' => $viewer->id === $booking->student_id ? 'student' : 'instructor',
        ]);

        return redirect()->away($url);
    }
}
