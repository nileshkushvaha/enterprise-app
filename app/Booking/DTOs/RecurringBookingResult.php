<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Models\Booking;
use App\Models\BookingSeries;
use Illuminate\Support\Collection;

/**
 * Outcome of a recurring booking request. Occurrences that clash with
 * availability are skipped and reported — the rest are booked.
 */
final readonly class RecurringBookingResult
{
    /**
     * @param  Collection<int, Booking>  $booked
     * @param  array<string, string>  $failures  ISO start => reason
     * @param  BookingSeries|null  $series  the recurring schedule that produced these
     *                                      classes. Null only on the legacy path, which created N
     *                                      independent bookings sharing a group uuid and no rule —
     *                                      $groupId keeps meaning exactly what it always did (and is the
     *                                      series id when a series exists), so existing readers are
     *                                      unaffected either way.
     * @param  int|null  $plannedCount  classes inside the schedule but beyond the
     *                                  confirmation horizon: real dates, not yet reserved. Null when the
     *                                  series is ongoing and therefore has no countable remainder.
     */
    public function __construct(
        public string $groupId,
        public Collection $booked,
        public array $failures,
        public ?BookingSeries $series = null,
        public ?int $plannedCount = null,
    ) {}

    public function allBooked(): bool
    {
        return $this->failures === [];
    }
}
