<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\DTOs\AvailabilityQueryData;
use App\Booking\DTOs\TimeSlotData;
use App\Booking\Exceptions\SlotUnavailableException;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

interface AvailabilityServiceInterface
{
    /**
     * Bookable slots for a host and booking type: availability windows
     * sliced by the type's duration, minus conflicting bookings.
     *
     * @return Collection<int, TimeSlotData>
     */
    public function slots(AvailabilityQueryData $query): Collection;

    /**
     * $bufferMinutes pads the slot on both sides against existing
     * bookings. Every slot is exclusive — one booking occupies it.
     *
     * @throws SlotUnavailableException when the slot cannot be booked
     */
    /**
     * Runs $work with a read cache over one instructor's calendar for
     * [$from, $to].
     *
     * For read-only passes only — a wizard preview, a schedule listing.
     * The cache covers the STATIC inputs (weekly windows, holidays,
     * leave, approval); bookings are never cached, so a pass that
     * creates them still sees each one. Always cleared, including when
     * $work throws.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    public function withCachedReads(int $instructorId, CarbonImmutable $from, CarbonImmutable $to, Closure $work): mixed;

    public function ensureAvailable(
        int $instructorId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $ignoreBookingId = null,
        int $bufferMinutes = 0,
    ): void;
}
