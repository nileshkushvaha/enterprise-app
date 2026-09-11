<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\DTOs\ZoomHostCapacityPreflightReport;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Repositories\MeetingHostReservationRepository;
use App\Models\Booking;
use App\Models\MeetingHostReservation;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * READ-ONLY. Computes what the preflight command prints, what the
 * backfill command works through, and what the settings page checks
 * before it lets an administrator switch capacity reservation on:
 *
 *  - is an active host registered;
 *  - which accepted (pending or confirmed) upcoming online bookings will
 *    run on Zoom — pinned to it, already carrying a Zoom meeting, or
 *    unpinned while Zoom is the default — and hold no active
 *    reservation ("missing allocations"). Generated recurring
 *    occurrences and pending-payment holds are bookings and are
 *    included; occurrences not yet generated reserve when they are;
 *  - where existing reservations plus those bookings' planned intervals
 *    exceed the pool's capacity ("overlaps").
 */
final class ZoomHostCapacityPreflightService
{
    public function __construct(
        private readonly MeetingHostReservationRepository $reservations,
        private readonly MeetingHostCapacityService $capacity,
        private readonly MeetingSettings $settings,
    ) {}

    public function report(int $horizonDays = 60): ZoomHostCapacityPreflightReport
    {
        $provider = ZoomMeetingProvider::KEY;
        $now = CarbonImmutable::now('UTC');
        $horizon = $now->addDays(max(1, $horizonDays));

        $hosts = $this->reservations->activePool($provider);
        $poolCapacity = (int) $hosts->sum('capacity');

        $unallocated = $this->reservations
            ->unallocatedUpcomingBookings($provider, $now, includeUnpinnedDefault: $this->settings->default_provider === $provider)
            ->filter(fn (Booking $booking): bool => $booking->starts_at->lessThan($horizon))
            ->values();

        $intervals = $this->reservations->activeReservationsFrom($provider, $now)
            ->map(fn (MeetingHostReservation $reservation): array => [
                'ref' => $reservation->booking?->reference ?? $reservation->booking_id,
                'from' => $reservation->occupies_from,
                'until' => $reservation->occupies_until,
            ])
            ->concat($unallocated->map(function (Booking $booking): array {
                [$from, $until] = $this->capacity->occupiedInterval($booking->starts_at, $booking->ends_at);

                return ['ref' => $booking->reference, 'from' => $from, 'until' => $until];
            }));

        return new ZoomHostCapacityPreflightReport(
            reservationEnabled: $this->settings->zoom_host_capacity_enabled,
            defaultProvider: $this->settings->default_provider,
            hosts: $hosts,
            poolCapacity: $poolCapacity,
            unallocated: $unallocated,
            overlaps: $this->overlapsExceeding($intervals, max(1, $poolCapacity)),
            horizonDays: $horizonDays,
        );
    }

    /**
     * Sweep-line over [from, until) intervals; each maximal window where
     * more than $capacity are open at once. Ends sort before starts at
     * the same instant, so touching intervals do not overlap.
     *
     * @param  Collection<int, array{ref: string, from: CarbonImmutable, until: CarbonImmutable}>  $intervals
     * @return Collection<int, array{from: CarbonImmutable, until: CarbonImmutable, count: int, refs: list<string>}>
     */
    private function overlapsExceeding(Collection $intervals, int $capacity): Collection
    {
        $events = [];

        foreach ($intervals as $interval) {
            $events[] = [$interval['from']->getTimestamp(), 1, $interval['ref'], $interval['from']];
            $events[] = [$interval['until']->getTimestamp(), -1, $interval['ref'], $interval['until']];
        }

        usort($events, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $open = [];
        $clusters = collect();
        $current = null;

        foreach ($events as [$ts, $delta, $ref, $at]) {
            if ($delta === 1) {
                $open[$ref] = true;
            } else {
                unset($open[$ref]);
            }

            $count = count($open);

            if ($count > $capacity && $current === null) {
                $current = ['from' => $at, 'count' => $count, 'refs' => array_keys($open)];
            } elseif ($current !== null && $count > $capacity) {
                $current['count'] = max($current['count'], $count);
                $current['refs'] = array_values(array_unique([...$current['refs'], ...array_keys($open)]));
            } elseif ($current !== null && $count <= $capacity) {
                $current['until'] = $at;
                $clusters->push($current);
                $current = null;
            }
        }

        return $clusters;
    }
}
