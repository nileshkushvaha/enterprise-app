<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Models\BookingMeeting;

/**
 * Which remote meeting a recording capture is working for, fixed at the
 * moment the row is claimed and re-checked at the moment it is
 * published — the two instants the meeting row is locked. Between them
 * the worker talks to the provider and to storage with no transaction
 * open, so the meeting row CAN change underneath it: a cancelled
 * meeting re-created on another provider, or on the same provider with
 * a new remote id. A recording is only ever published for the meeting
 * it was captured for.
 */
final readonly class MeetingIdentitySnapshot
{
    public function __construct(
        public string $provider,
        public ?string $providerMeetingId,
        public ?string $providerEventId,
    ) {}

    public static function of(BookingMeeting $meeting): self
    {
        return new self(
            (string) $meeting->provider,
            $meeting->provider_meeting_id !== null ? (string) $meeting->provider_meeting_id : null,
            $meeting->provider_event_id !== null ? (string) $meeting->provider_event_id : null,
        );
    }

    /** Same provider and the same remote identifiers. Status is deliberately not part of identity: a meeting cancelled after the lesson ran is still the meeting that was recorded. */
    public function matches(BookingMeeting $meeting): bool
    {
        return $this->provider === (string) $meeting->provider
            && $this->providerMeetingId === ($meeting->provider_meeting_id !== null ? (string) $meeting->provider_meeting_id : null)
            && $this->providerEventId === ($meeting->provider_event_id !== null ? (string) $meeting->provider_event_id : null);
    }

    public function describe(): string
    {
        return sprintf('%s/%s', $this->provider, $this->providerMeetingId ?? $this->providerEventId ?? 'no remote id');
    }
}
