<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use Carbon\CarbonImmutable;

/**
 * What a provider says about ONE remote meeting, read without changing
 * it — the evidence an adoption is checked against before anything is
 * written. Only identity fields: who hosts it, when it starts, and the
 * agenda SIRI itself wrote (the booking reference). Never a join or
 * start URL, never a passcode.
 */
final readonly class RemoteMeetingIdentity
{
    public function __construct(
        public string $providerMeetingId,
        /** The provider's host user id, when reported. */
        public ?string $hostId,
        /** The provider's host user email, when reported. */
        public ?string $hostEmail,
        public ?string $agenda,
        public ?CarbonImmutable $startsAt,
    ) {}

    /** The booking reference the agenda names, if SIRI wrote one there. */
    public function bookingReferenceInAgenda(): ?string
    {
        if ($this->agenda === null || preg_match('/Booking reference:\s*(BK-[A-Z0-9]+)/', $this->agenda, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** Does the meeting run under this platform host identity (Zoom user id OR email)? */
    public function isHostedBy(string $hostReference): bool
    {
        $reference = strtolower(trim($hostReference));

        return ($this->hostId !== null && strtolower($this->hostId) === $reference)
            || ($this->hostEmail !== null && strtolower($this->hostEmail) === $reference);
    }
}
