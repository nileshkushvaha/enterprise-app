<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Support\Timezone\LocalWallClock;
use Carbon\CarbonImmutable;

/**
 * One date produced by a recurrence rule, BEFORE anything is booked.
 *
 * It carries both readings on purpose. `$localDateTime` is the wall
 * clock the series MEANS ("every Monday at 18:00 in Europe/London") and
 * is the only thing that survives a daylight-saving transition
 * unchanged; `$startsAt` is the UTC instant that reading resolves to,
 * and is null exactly when the reading does not exist that day (spring
 * forward). Deriving one from the other after the fact is impossible —
 * Carbon silently normalises a skipped reading — so both are kept.
 */
final readonly class RecurrenceOccurrenceData
{
    public function __construct(
        /** 1-based position in the series, counting only non-skipped dates. */
        public int $sequence,
        /** `Y-m-d` in the recurrence timezone — the series' own calendar. */
        public string $localDate,
        /** `Y-m-d H:i:s` as the series intends it locally. */
        public string $localDateTime,
        /** The series' own timezone — the clock $localDateTime is read in. */
        public string $timezone,
        /** Null when $wallClock is not VALID: the reading has no single instant. */
        public ?CarbonImmutable $startsAt,
        public string $wallClock = LocalWallClock::VALID,
    ) {}

    public function isRepresentable(): bool
    {
        return $this->wallClock === LocalWallClock::VALID && $this->startsAt !== null;
    }

    public function endsAt(int $durationMinutes): ?CarbonImmutable
    {
        return $this->startsAt?->addMinutes($durationMinutes);
    }
}
