<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\DTOs\MeetingCreationContext;
use App\Booking\DTOs\MeetingCreationResult;
use App\Booking\DTOs\RemoteMeetingIdentity;
use App\Booking\DTOs\RemoteMeetingReconciliation;
use App\Booking\Exceptions\BookingException;
use App\Models\Booking;

/**
 * A meeting provider that can be asked "what do you already hold for
 * this booking?" after a create whose outcome was AMBIGUOUS (the request
 * left this host and no answer came back), and that can adopt a
 * specific remote meeting once its identity is established.
 *
 * Reconciliation is a QUERY, never a write. It reports what was
 * established (RemoteMeetingReconciliation). Only a `found` answer —
 * exactly one remote meeting carrying the booking reference — may be
 * adopted automatically; `none` and `inconclusive` fail closed and wait
 * for an operator, because a missing search result does not prove the
 * original create failed.
 */
interface ReconcilesAmbiguousMeetings
{
    /**
     * @throws BookingException when the provider cannot be asked right now
     */
    public function findExistingMeeting(Booking $booking, MeetingCreationContext $context): RemoteMeetingReconciliation;

    /**
     * Identity of one remote meeting, read without changing it, or null
     * when the provider holds no meeting with that id. Adoption is
     * checked against this BEFORE anything is written.
     *
     * @throws BookingException when the provider cannot be asked right now
     */
    public function describeExistingMeeting(string $providerMeetingId, MeetingCreationContext $context): ?RemoteMeetingIdentity;

    /**
     * Take ownership of an existing remote meeting for this booking —
     * the one reconciliation found, or one an operator identified by
     * id — aligning its schedule with the booking and returning its
     * state exactly as a fresh create would.
     *
     * @throws BookingException when the meeting cannot be read or aligned
     */
    public function adoptExistingMeeting(Booking $booking, string $providerMeetingId, MeetingCreationContext $context): MeetingCreationResult;
}
