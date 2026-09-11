<?php

declare(strict_types=1);

namespace App\Booking\Exceptions;

/**
 * A booking already has a CREATED meeting on one provider and someone
 * asked for a different one. Replacing a live meeting participants may
 * already hold links to is not supported — it would need the old
 * meeting cancelled at its provider, capacity re-checked and every
 * link re-issued, and nothing does that today. The refusal is explicit
 * so an administrator is never told "Meeting created" about a meeting
 * that was left exactly as it was.
 */
final class MeetingProviderSwitchNotSupportedException extends BookingException
{
    public static function between(string $reference, string $current, string $requested): self
    {
        return new self(sprintf(
            'Booking %s already has a created %s meeting. Switching it to %s is not supported: the existing meeting is kept and participants keep their current link. To test %s, pin a booking whose meeting has not been created yet (php artisan meetings:pin-provider), or cancel this booking and book again.',
            $reference,
            $current,
            $requested,
            $requested,
        ));
    }
}
