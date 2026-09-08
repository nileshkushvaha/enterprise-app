<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Models\BookingMeeting;

/**
 * OPTIONAL provider capability: can this provider actually shut a
 * lesson's meeting down once its join window has closed?
 *
 * Separate from MeetingProviderInterface for the same reason
 * MeetingRecordingProviderInterface is: a provider that cannot do it
 * (the manual link an admin pasted, a Calendar-created conference)
 * must simply not implement it, never throw or pretend.
 *
 * Withholding the join URL after the window (BookingMeetingService's
 * one window calculation) governs what SIRI hands out; this governs
 * what stays alive at the provider, which is what actually stops a
 * class — and its automatic recording — from running on past its
 * scheduled end.
 */
interface EndsActiveMeetings
{
    /**
     * Ends the meeting at the provider and, where the provider allows
     * it, locks it so the same link cannot start another one.
     *
     * Returns false when this particular meeting carries nothing this
     * provider can act on (e.g. a Google lesson whose conference was
     * created by Calendar rather than the Meet API) — a normal,
     * expected outcome, not a failure. Failures throw.
     *
     * Must be safe to call more than once for the same meeting.
     */
    public function endActiveMeeting(BookingMeeting $meeting): bool;
}
