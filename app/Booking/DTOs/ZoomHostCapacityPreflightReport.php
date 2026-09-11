<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Models\Booking;
use App\Models\PlatformMeetingHost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The read-only answer to "may Zoom host capacity reservation be
 * switched on, and what stands in the way?" — shared by the preflight
 * command, the backfill command and the settings page's activation
 * gate, so all three agree on what a finding is.
 */
final readonly class ZoomHostCapacityPreflightReport
{
    /**
     * @param  Collection<int, PlatformMeetingHost>  $hosts
     * @param  Collection<int, Booking>  $unallocated  upcoming Zoom-bound bookings holding no active reservation
     * @param  Collection<int, array{from: CarbonImmutable, until: CarbonImmutable, count: int, refs: list<string>}>  $overlaps
     */
    public function __construct(
        public bool $reservationEnabled,
        public string $defaultProvider,
        public Collection $hosts,
        public int $poolCapacity,
        public Collection $unallocated,
        public Collection $overlaps,
        public int $horizonDays,
    ) {}

    public function hasHost(): bool
    {
        return $this->hosts->isNotEmpty();
    }

    public function hasFindings(): bool
    {
        return ! $this->hasHost() || $this->unallocated->isNotEmpty() || $this->overlaps->isNotEmpty();
    }

    /** One line an operator can act on, for notifications and logs. */
    public function summary(): string
    {
        if (! $this->hasHost()) {
            return 'No active Zoom host is registered (run meetings:zoom-hosts:register).';
        }

        $parts = [];

        if ($this->unallocated->isNotEmpty()) {
            $parts[] = sprintf('%d upcoming Zoom booking(s) hold no host reservation (run meetings:zoom-hosts:backfill)', $this->unallocated->count());
        }

        if ($this->overlaps->isNotEmpty()) {
            $parts[] = sprintf('%d planned window(s) exceed the pool capacity of %d', $this->overlaps->count(), $this->poolCapacity);
        }

        return $parts === [] ? 'No findings.' : implode('; ', $parts).'.';
    }
}
