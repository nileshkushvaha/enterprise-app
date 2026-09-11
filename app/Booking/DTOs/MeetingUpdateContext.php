<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use Carbon\CarbonImmutable;

/**
 * Input to MeetingProviderInterface::updateMeeting() — an admin editing
 * an existing meeting record (manual link change) or a retry/sync
 * attempt (Google). Every field is optional: null means "leave
 * unchanged", not "clear".
 */
final readonly class MeetingUpdateContext
{
    public function __construct(
        public ?int $requestedBy = null,
        public ?string $providerLabel = null,
        public ?string $joinUrl = null,
        public ?string $password = null,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $endsAt = null,
        public ?string $timezone = null,
        /**
         * The platform host identity the remote meeting must be created
         * under (a Zoom user id/email from platform_meeting_hosts). Null
         * lets the provider fall back to its configured default host.
         * Set by BookingMeetingService from the booking's capacity
         * reservation, so the host that was reserved is the host used.
         */
        public ?string $hostReference = null,
    ) {}

    /** The same intent re-expressed as a first creation — used when no meeting row exists yet. */
    public function toCreationContext(): MeetingCreationContext
    {
        return new MeetingCreationContext(
            requestedBy: $this->requestedBy,
            providerLabel: $this->providerLabel,
            joinUrl: $this->joinUrl,
            password: $this->password,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            timezone: $this->timezone,
            hostReference: $this->hostReference,
        );
    }
}
