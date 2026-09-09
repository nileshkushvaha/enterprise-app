<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\AvailabilityServiceInterface;
use App\Booking\Contracts\BookingRepositoryInterface;
use App\Booking\DTOs\RecurrenceOccurrenceData;
use App\Booking\DTOs\SeriesOccurrenceData;
use App\Booking\Enums\SeriesOccurrenceStatus;
use App\Booking\Exceptions\SlotUnavailableException;
use App\Booking\Validation\Rules\BookingWindowRule;
use App\Support\Timezone\LocalWallClock;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Answers one question for one date: can this class actually happen,
 * and if not, why — in words a student can act on.
 *
 * The rule against silent substitution lives here by construction:
 * this class is only ever given the instructor the series already has,
 * and it has no way to look for another one. A date the assigned
 * instructor cannot teach comes back as a conflict for the student to
 * resolve; it is never quietly reassigned, never shifted to a nearby
 * time, and never dropped.
 *
 * It is also the same set of checks the booking engine itself runs at
 * creation. That is deliberate rather than duplicated: the preview
 * calls AvailabilityService::ensureAvailable(), the identical method
 * BookingService::request() runs under the instructor lock, so a
 * preview cannot promise something creation would refuse. The preview
 * is advisory by nature — it runs outside the lock — which is exactly
 * why creation re-runs it under the lock rather than trusting it.
 */
final class SeriesOccurrenceConflictChecker
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availability,
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingWindowRule $window,
    ) {}

    /**
     * @param  bool  $enforceWindow  false while previewing dates that sit beyond the
     *                               confirmation horizon: those are not being booked yet, so judging
     *                               them against the advance-booking limit would report a conflict
     *                               that does not exist and will not exist by the time they are booked.
     */
    /**
     * Runs $work with the instructor's static availability loaded once
     * for [$from, $to] instead of re-read for every date.
     *
     * Read-only by contract — see AvailabilityServiceInterface::
     * withCachedReads(). Bookings are still read live, so a conflict
     * created between two dates of the same pass is still seen.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    public function batched(int $instructorId, CarbonImmutable $from, CarbonImmutable $to, Closure $work): mixed
    {
        return $this->availability->withCachedReads($instructorId, $from, $to, $work);
    }

    public function evaluate(
        RecurrenceOccurrenceData $occurrence,
        int $instructorId,
        int $studentId,
        int $durationMinutes,
        int $bufferMinutes = 0,
        bool $enforceWindow = true,
        ?string $ignoreBookingId = null,
        bool $isDemo = false,
    ): SeriesOccurrenceData {
        $base = new SeriesOccurrenceData(
            sequence: $occurrence->sequence,
            localDate: $occurrence->localDate,
            localDateTime: $occurrence->localDateTime,
            startsAt: $occurrence->startsAt,
            endsAt: $occurrence->endsAt($durationMinutes),
            status: SeriesOccurrenceStatus::Available,
        );

        // Checked first because there is no instant to check anything
        // else against: the wall clock the series means does not exist,
        // or exists twice, on this date.
        if (! $occurrence->isRepresentable()) {
            return $base->with(
                status: SeriesOccurrenceStatus::UnrepresentableTime,
                reason: LocalWallClock::reason($occurrence->wallClock, $occurrence->timezone),
            );
        }

        $startsAt = $occurrence->startsAt;
        $endsAt = $base->endsAt;

        // The same bookable-window rule creation enforces (minimum
        // notice, maximum advance), so the preview cannot offer a date
        // BookingService::request() would refuse.
        //
        // The two ways of failing it are NOT the same thing, and
        // collapsing them produces nonsense: a date that is merely too
        // far ahead would be reported as a conflict the student cannot
        // do anything about, sitting between dates that are fine — and
        // it will become bookable on its own, simply by time passing.
        // Too far ahead is Planned; too soon (or already gone) is a real
        // conflict.
        if ($enforceWindow && ! $this->window->isWithinWindow($startsAt, isDemo: $isDemo)) {
            $tooSoon = $startsAt->lessThan(
                CarbonImmutable::now()->addMinutes($this->window->noticeMinutes($isDemo)),
            );

            if (! $tooSoon) {
                return $base->with(status: SeriesOccurrenceStatus::Planned);
            }

            return $base->with(
                status: SeriesOccurrenceStatus::OutsideBookingWindow,
                reason: $startsAt->isPast()
                    ? 'This date has already passed.'
                    : 'This class starts too soon to be booked.',
            );
        }

        try {
            $this->availability->ensureAvailable(
                $instructorId,
                $startsAt,
                $endsAt,
                ignoreBookingId: $ignoreBookingId,
                bufferMinutes: $bufferMinutes,
            );
        } catch (SlotUnavailableException) {
            return $base->with(
                status: SeriesOccurrenceStatus::InstructorUnavailable,
                reason: 'Your instructor is not available at this time on this date.',
            );
        }

        if ($this->bookings->studentHasOverlap($studentId, $startsAt, $endsAt, $ignoreBookingId)) {
            return $base->with(
                status: SeriesOccurrenceStatus::StudentBusy,
                reason: 'You already have another class booked at this time.',
            );
        }

        return $base;
    }
}
