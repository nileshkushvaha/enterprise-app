<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\DTOs\MeetingUpdateContext;
use App\Booking\Enums\MeetingJoinAvailability;
use App\Lessons\Enums\LessonStatus;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Creates and stores meeting details for confirmed bookings, idempotently
 * and safely. createMeeting()/saveManualMeeting() never throw — failures
 * are recorded on the meeting record (status = failed) and logged, never
 * surfaced as an exception to callers triggered from queued listeners or
 * admin actions.
 */
interface BookingMeetingServiceInterface
{
    /**
     * Automatic trigger (BookingConfirmed listener) and admin
     * "Create/Retry Meeting". $providerKey null uses
     * MeetingSettings::default_provider; an explicit key (e.g.
     * 'google_meet') is an admin override. Idempotent: returns the
     * existing meeting untouched if already `created`. Returns null
     * only when the booking is ineligible and no meeting row exists yet.
     */
    public function createMeeting(Booking $booking, ?string $providerKey = null): ?BookingMeeting;

    /**
     * Admin manual create/update — always uses ManualMeetingProvider
     * regardless of MeetingSettings::default_provider. Still enforces
     * booking eligibility and MeetingSettings::manual_provider_enabled.
     */
    public function saveManualMeeting(Booking $booking, MeetingUpdateContext $context): ?BookingMeeting;

    /** Admin "Mark Meeting Cancelled". Null if no meeting exists. */
    public function cancelMeeting(Booking $booking): ?BookingMeeting;

    /** Whether $booking currently qualifies for meeting creation. */
    public function isEligible(Booking $booking): bool;

    public function findForBooking(Booking $booking): ?BookingMeeting;

    /**
     * Whether $booking's meeting may be joined right now by a participant
     * whose role-level join-link visibility is $roleVisible (e.g.
     * MeetingSettings::instructor_join_url_visible). Reads $booking's
     * already-loaded `meeting` relation — callers iterating a list must
     * eager-load it to avoid N+1. $lessonStatus, when given, closes
     * access once the lesson is no longer open (e.g. Completed).
     */
    public function joinAvailabilityFor(Booking $booking, bool $roleVisible, ?LessonStatus $lessonStatus = null): MeetingJoinAvailability;

    /** The join URL when joinAvailabilityFor() would return Available, otherwise null. */
    public function joinUrlFor(Booking $booking, bool $roleVisible, ?LessonStatus $lessonStatus = null): ?string;

    /**
     * The complete authoritative STUDENT meeting-URL disclosure
     * decision: null unless the viewer is the
     * booking's student, passes the strict Active-lifecycle guard, and
     * joinAvailabilityFor() resolves Available under the student
     * visibility setting (Confirmed/Created, non-blank URL, and the
     * current time inside the configured before/after visibility
     * window). Student-facing surfaces and notifications must use this
     * — never meeting->join_url directly.
     */
    public function studentJoinUrlFor(Booking $booking, ?User $viewer): ?string;

    /**
     * The instant this meeting's join window closes: its scheduled end
     * plus MeetingSettings::meeting_link_visible_after_minutes. The same
     * value joinAvailabilityFor() stops returning Available at — one
     * calculation, never a second copy — and the instant from which
     * closeExpiredMeeting() may shut the meeting down at the provider.
     */
    public function joinWindowEndsAt(BookingMeeting $meeting): ?CarbonImmutable;

    /**
     * Ends a finished lesson's meeting at the provider once its join
     * window has passed, so a class (and any automatic recording) cannot
     * run on indefinitely.
     *
     * Returns true only when the provider actually closed something.
     * False covers every legitimate "nothing to do": auto-close off, the
     * window still open, a meeting that is not Created, a provider with
     * no closing capability, or a meeting already closed. Provider
     * failures throw, so the sweep can retry them.
     */
    public function closeExpiredMeeting(BookingMeeting $meeting): bool;
}
