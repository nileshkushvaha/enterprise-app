<?php

declare(strict_types=1);

namespace App\Booking\Events;

use App\Models\BookingSeries;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A class the schedule owes could not be booked when its turn came.
 *
 * Raised only by the BACKGROUND pass. Interactively the student is
 * shown every conflict before confirming and resolves it there; this is
 * the case they cannot see — a date that was fine when they booked and
 * has since stopped being (the instructor took leave, the slot went).
 *
 * Recording it in the series view is not enough on its own: a student
 * has no reason to open a page to check for something they were never
 * told about. The event exists so the ordinary participant-notification
 * pipeline (SendBookingNotifications) can tell them, on the same
 * channels and with the same idempotency guarantees as every other
 * booking message.
 *
 * ShouldDispatchAfterCommit: the exception row that makes this durable
 * is written first, so a listener must never observe the event before
 * the record it refers to exists.
 */
final class BookingSeriesOccurrenceUnavailable implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly BookingSeries $series,
        /** `Y-m-d` in the SERIES' timezone — the date the student sees in their schedule. */
        public readonly string $localDate,
        /** The instant the class would have started, or null if daylight saving deleted the reading. */
        public readonly ?CarbonImmutable $startsAt,
        /** Student-facing explanation, already free of implementation vocabulary. */
        public readonly ?string $reason = null,
    ) {}
}
