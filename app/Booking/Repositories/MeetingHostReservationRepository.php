<?php

declare(strict_types=1);

namespace App\Booking\Repositories;

use App\Booking\Enums\BookingLocationType;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingHostReservationStatus;
use App\Booking\Enums\MeetingStatus;
use App\Models\Booking;
use App\Models\MeetingHostReservation;
use App\Models\PlatformMeetingHost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Database access for platform hosts and their capacity reservations.
 * Every locking read here is meant to run INSIDE the caller's
 * transaction (MeetingHostCapacityService owns the lock order); nothing
 * in this class decides policy.
 */
final class MeetingHostReservationRepository
{
    /**
     * The provider's active hosts, row-locked in a fixed order. Taking
     * these locks is what serializes every capacity decision for the
     * provider across instructors, workers and processes: whoever holds
     * them reads and writes reservations alone until commit.
     *
     * @return Collection<int, PlatformMeetingHost>
     */
    public function lockActivePool(string $provider): Collection
    {
        return PlatformMeetingHost::query()->activePool($provider)->lockForUpdate()->get();
    }

    /** @return Collection<int, PlatformMeetingHost> */
    public function activePool(string $provider): Collection
    {
        return PlatformMeetingHost::query()->activePool($provider)->get();
    }

    /**
     * Active reservations on one host overlapping [$from, $until), read
     * with a locking read so the count reflects the LATEST committed
     * rows, not this transaction's earlier snapshot — under REPEATABLE
     * READ a plain SELECT after acquiring the host lock could still
     * return a stale count and let two bookings through.
     */
    public function activeOverlapCount(
        string $hostId,
        CarbonImmutable $from,
        CarbonImmutable $until,
        ?string $ignoreBookingId = null,
    ): int {
        return MeetingHostReservation::query()
            ->active()
            ->where('platform_meeting_host_id', $hostId)
            ->where('occupies_from', '<', $until->utc())
            ->where('occupies_until', '>', $from->utc())
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('booking_id', '!=', $ignoreBookingId))
            ->lockForUpdate()
            ->count();
    }

    public function activeForBooking(string $bookingId, bool $lock = false): ?MeetingHostReservation
    {
        return MeetingHostReservation::query()
            ->active()
            ->where('booking_id', $bookingId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): MeetingHostReservation
    {
        return MeetingHostReservation::query()->create($attributes);
    }

    public function release(MeetingHostReservation $reservation, string $reason): MeetingHostReservation
    {
        $reservation->fill([
            'status' => MeetingHostReservationStatus::Released,
            'released_at' => now(),
            'release_reason' => $reason,
        ])->save();

        return $reservation;
    }

    // ── Preflight (read-only) ─────────────────────────────────────────

    /**
     * Accepted (pending or confirmed) future bookings that will run on
     * $provider — because they were accepted for it, or already carry a
     * meeting on it — and hold NO active reservation.
     *
     * @return Collection<int, Booking>
     */
    public function unallocatedUpcomingBookings(string $provider, CarbonImmutable $from, bool $includeUnpinnedDefault = false): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('location_type', BookingLocationType::Online)
            ->where('ends_at', '>', $from->utc())
            ->where(function ($query) use ($provider, $includeUnpinnedDefault): void {
                // Accepted for the provider, or already carrying its meeting …
                $query->where('meeting_provider_intent', $provider)
                    ->orWhereHas('meeting', fn ($meeting) => $meeting->where('provider', $provider));

                // … or accepted with no pin at all while the provider is
                // the default: meeting creation would route them there.
                if ($includeUnpinnedDefault) {
                    $query->orWhere(fn ($unpinned) => $unpinned->whereNull('meeting_provider_intent')->whereDoesntHave('meeting'));
                }
            })
            // A booking already served by a CREATED meeting on another
            // provider is resolved, whatever its pin says.
            ->whereDoesntHave('meeting', fn ($meeting) => $meeting->where('provider', '!=', $provider)->where('status', MeetingStatus::Created))
            ->whereDoesntHave('hostReservations', fn ($query) => $query->active())
            ->with('meeting')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Every active reservation on the provider's hosts ending after
     * $from, ordered for a sweep-line overlap check.
     *
     * @return Collection<int, MeetingHostReservation>
     */
    public function activeReservationsFrom(string $provider, CarbonImmutable $from): Collection
    {
        return MeetingHostReservation::query()
            ->active()
            ->where('provider', $provider)
            ->where('occupies_until', '>', $from->utc())
            ->orderBy('platform_meeting_host_id')
            ->orderBy('occupies_from')
            ->get();
    }
}
