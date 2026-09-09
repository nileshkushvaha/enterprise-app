<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\Contracts\BookingRepositoryInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\Contracts\BookingTypeRepositoryInterface;
use App\Booking\DTOs\CancelBookingData;
use App\Booking\DTOs\CreateBookingData;
use App\Booking\DTOs\CreateBookingSeriesData;
use App\Booking\DTOs\RecurrenceOccurrenceData;
use App\Booking\DTOs\RecurrenceRuleData;
use App\Booking\DTOs\RecurringBookingResult;
use App\Booking\DTOs\RescheduleBookingData;
use App\Booking\DTOs\SeriesOccurrenceData;
use App\Booking\DTOs\SeriesSchedulePreviewData;
use App\Booking\Enums\BookingActor;
use App\Booking\Enums\BookingSeriesStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\SeriesChangeScope;
use App\Booking\Enums\SeriesOccurrenceStatus;
use App\Booking\Events\BookingSeriesOccurrenceUnavailable;
use App\Booking\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\BookingSeriesException;
use App\Settings\BookingSettings;
use App\Support\Timezone\LocalWallClock;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Owns recurring schedules end to end: previewing them, creating them,
 * generating their classes over time, and changing or ending them.
 *
 * ── Why a horizon instead of a session cap ──────────────────────────
 *
 * The old design created every occurrence up front, which forced a cap
 * (twelve) because "every occurrence" has to be finite and bounded.
 * Here the SCHEDULE is stored as a rule and the CLASSES are created
 * rolling-forward inside a confirmation horizon. A student can ask for
 * forty classes, or for a schedule with no end at all; what is bounded
 * is how much calendar the platform holds at once, which is an
 * operational property rather than a limit on the student.
 *
 * The horizon is never wider than the platform's own bookable window
 * (BookingSettings::$maximum_advance_booking_days) — a series must not
 * be a way to book further ahead than anything else can.
 *
 * ── Idempotence ─────────────────────────────────────────────────────
 *
 * Generation is safe to retry, run twice, or race with itself. Three
 * things make that true, in order of authority:
 *
 *   1. `bookings (booking_series_id, series_occurrence_date)` is UNIQUE.
 *      Two workers attempting the same occurrence produce one row and
 *      one caught constraint violation, not two classes and two
 *      payment demands.
 *   2. `booking_series.generated_through_date` is a watermark, so a
 *      resumed run does not re-walk work already decided.
 *   3. A cancelled occurrence keeps its booking row, so regeneration
 *      cannot resurrect a class the student called off.
 *
 * ── What happens to a date that does not work ───────────────────────
 *
 * Nothing is ever silently skipped, shifted, or given to a different
 * instructor. Interactively (the wizard), conflicts are shown before
 * confirmation and the student resolves each one. In the background,
 * an occurrence that has become unavailable since the series was
 * created is recorded as a durable exception carrying its reason — it
 * appears in the student's series view rather than vanishing — and,
 * for a series defined by a class COUNT, the schedule reaches one date
 * further so the student still receives the number of classes they
 * asked for. A series defined by an END DATE ends on that date, so a
 * lost date means one class fewer. Which of the two applies is stated
 * to the student before they confirm.
 */
final class BookingSeriesService
{
    /** Occurrences returned per preview page. */
    public const int PREVIEW_PAGE_SIZE = 20;

    public function __construct(
        private readonly RecurrenceScheduler $scheduler,
        private readonly SeriesOccurrenceConflictChecker $conflicts,
        private readonly BookingServiceInterface $bookings,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingTypeRepositoryInterface $types,
        private readonly BookingSettings $settings,
    ) {}

    /**
     * How far ahead classes are actually reserved.
     *
     * Clamped to the bookable window because that window is the
     * platform's outer limit for every flow — a recurring schedule
     * must not become a back door past it.
     */
    /**
     * Whether this deployment may accept schedules that reach past the
     * confirmation horizon.
     *
     * Server-side and checked at creation, not merely reflected in the
     * UI: such a schedule is a promise, and the promise is only good if
     * the background pass is actually running here. See
     * BookingSettings::$recurring_future_generation_enabled.
     */
    public function futureGenerationEnabled(): bool
    {
        return $this->settings->recurring_future_generation_enabled;
    }

    /**
     * Does this rule owe classes that cannot be reserved right now?
     *
     * True for an ongoing schedule (which by definition never finishes)
     * and for any finite one whose last class falls beyond the horizon.
     * The two are the same problem — classes promised but not held — so
     * they are answered together rather than by special-casing "until I
     * cancel".
     *
     * @param  list<string>  $skippedDates
     * @param  array<string, string>  $timeOverrides
     */
    public function requiresFutureGeneration(RecurrenceRuleData $rule, array $skippedDates = [], array $timeOverrides = []): bool
    {
        if (! $rule->endCondition->isFinite()) {
            return true;
        }

        $last = $this->scheduler->lastLocalDate($rule, $skippedDates);

        // No knowable last date (a count so large it has no meaningful
        // end) counts as needing future generation — the honest answer
        // when we cannot say otherwise.
        return $last === null || $last > $this->horizonDate($rule);
    }

    /**
     * Refuses a schedule this deployment cannot yet honour.
     *
     * Deliberately a refusal, not a trim. Booking the part that fits and
     * dropping the rest would hand the student a shorter schedule than
     * the one they asked for with nothing to tell them so — exactly the
     * silent truncation the horizon exists to prevent. It is also not a
     * class cap: with the flag on there is no limit at all.
     *
     * @param  list<string>  $skippedDates
     * @param  array<string, string>  $timeOverrides
     *
     * @throws BookingException
     */
    public function assertConfirmable(RecurrenceRuleData $rule, array $skippedDates = [], array $timeOverrides = []): void
    {
        if ($this->futureGenerationEnabled()) {
            return;
        }

        if (! $this->requiresFutureGeneration($rule, $skippedDates, $timeOverrides)) {
            return;
        }

        throw new BookingException(sprintf(
            $rule->endCondition->isFinite()
                ? 'This schedule runs further ahead than we can confirm at the moment — we can currently book classes up to %s. Please choose an end date on or before then, or fewer classes; you can extend the schedule later.'
                : 'Open-ended schedules are not available just yet. Please choose a number of classes, or an end date on or before %s — you can extend the schedule later.',
            CarbonImmutable::parse($this->horizonDate($rule))->format('j F Y'),
        ));
    }

    public function horizonDays(): int
    {
        return max(1, min(
            $this->settings->recurring_confirmation_horizon_days,
            $this->settings->maximum_advance_booking_days,
        ));
    }

    /**
     * The last local date of $rule that classes are reserved through.
     *
     * Measured from the SERIES' start, not from today, once the start is
     * in the future. A schedule beginning in six months would otherwise
     * confirm nothing at all at the moment the student books it — they
     * would be shown a schedule and given no classes — which is the
     * opposite of what a horizon is for. The horizon bounds how much
     * calendar is held at once; it is not a rule about how soon a
     * schedule must begin.
     *
     * Still clamped to the platform's bookable window measured from
     * today, so a series can never reserve further ahead than any other
     * booking may.
     */
    public function horizonDate(RecurrenceRuleData $rule): string
    {
        $today = CarbonImmutable::now($rule->timezone)->startOfDay();
        $start = $rule->startLocalDate();
        $anchor = $start->greaterThan($today) ? $start : $today;

        $horizon = $anchor->addDays($this->horizonDays());
        $outerLimit = $today->addDays($this->settings->maximum_advance_booking_days);

        return ($horizon->greaterThan($outerLimit) ? $outerLimit : $horizon)->toDateString();
    }

    // ── Preview ─────────────────────────────────────────────────────────

    /**
     * The schedule a rule would produce, with every date inside the
     * horizon actually checked against the assigned instructor's
     * availability and the student's own calendar.
     *
     * Dates beyond the horizon are reported as Planned and deliberately
     * NOT checked: availability that far out is not knowable yet, and
     * presenting an unchecked date as "available" would be a promise
     * the platform cannot keep.
     *
     * @param  list<string>  $skippedDates
     */
    public function preview(
        RecurrenceRuleData $rule,
        int $instructorId,
        int $studentId,
        int $durationMinutes,
        int $bufferMinutes = 0,
        array $skippedDates = [],
        int $page = 1,
        int $perPage = self::PREVIEW_PAGE_SIZE,
        bool $isDemo = false,
        array $timeOverrides = [],
    ): SeriesSchedulePreviewData {
        $horizonDate = $this->horizonDate($rule);
        $total = $this->scheduler->totalOccurrences($rule, $skippedDates);

        // One page, plus one extra row purely to answer "is there more?"
        // without enumerating (and conflict-checking) the whole series.
        $offset = max(0, ($page - 1) * $perPage);
        $window = $this->scheduler->occurrences($rule, $offset + $perPage + 1, null, $skippedDates, $timeOverrides);
        $hasMore = count($window) > $offset + $perPage;
        $pageOccurrences = array_slice($window, $offset, $perPage);

        // The instructor's windows, holidays and leave cannot change
        // between the first date of this page and the last, so they are
        // read once for the whole page rather than per date. Bookings
        // are still read live.
        $evaluated = $this->batchedOver($pageOccurrences, $instructorId, $durationMinutes, function () use ($pageOccurrences, $horizonDate, $instructorId, $studentId, $durationMinutes, $bufferMinutes, $isDemo): array {
            $rows = [];

            foreach ($pageOccurrences as $occurrence) {
                $rows[] = $occurrence->localDate > $horizonDate
                    ? $this->plannedOccurrence($occurrence, $durationMinutes)
                    : $this->conflicts->evaluate(
                        $occurrence,
                        $instructorId,
                        $studentId,
                        $durationMinutes,
                        $bufferMinutes,
                        isDemo: $isDemo,
                    );
            }

            return $rows;
        });

        // A date the student removed is no longer part of the schedule,
        // so the scheduler does not produce it — but it still has to be
        // VISIBLE, or "Put back" is unreachable and the removal is
        // effectively irreversible. Merged back in for display only: it
        // carries no sequence number, because it occupies no place in
        // the schedule.
        $evaluated = $this->withSkippedDatesShown($evaluated, $skippedDates, $durationMinutes, $rule);

        // Counted across the WHOLE schedule inside the horizon, not just
        // this page: a student paging through must not see the "3
        // conflicts" badge change because they scrolled.
        [$conflictCount, $bookableNow, $planned] = $this->tallySchedule(
            $rule,
            $instructorId,
            $studentId,
            $durationMinutes,
            $bufferMinutes,
            $skippedDates,
            $horizonDate,
            $isDemo,
            $timeOverrides,
        );

        return new SeriesSchedulePreviewData(
            occurrences: $evaluated,
            totalScheduled: $total,
            conflictCount: $conflictCount,
            bookableNowCount: $bookableNow,
            plannedCount: $planned,
            horizonDays: $this->horizonDays(),
            lastLocalDate: $this->scheduler->lastLocalDate($rule, $skippedDates),
            hasMore: $hasMore,
            instructorId: $instructorId,
            timezone: $rule->timezone,
            requiresFutureGeneration: $this->requiresFutureGeneration($rule, $skippedDates, $timeOverrides),
            futureGenerationAvailable: $this->futureGenerationEnabled(),
        );
    }

    /**
     * Whole-schedule counts. Only dates inside the horizon are checked;
     * everything past it is counted as planned without a query, which is
     * what keeps this bounded for a long or ongoing series.
     *
     * @param  list<string>  $skippedDates
     * @return array{int, int, int} conflicts, bookable now, planned
     */
    private function tallySchedule(
        RecurrenceRuleData $rule,
        int $instructorId,
        int $studentId,
        int $durationMinutes,
        int $bufferMinutes,
        array $skippedDates,
        string $horizonDate,
        bool $isDemo,
        array $timeOverrides = [],
    ): array {
        // Everything that could be reserved now lies inside the horizon,
        // so enumerating to the horizon (never to the end of an ongoing
        // series) is enough to decide both checked counts.
        $withinHorizon = [];

        foreach ($this->scheduler->occurrences($rule, self::maxHorizonOccurrences(), null, $skippedDates, $timeOverrides) as $occurrence) {
            if ($occurrence->localDate > $horizonDate) {
                break;
            }

            $withinHorizon[] = $occurrence;
        }

        $conflicts = 0;
        $bookable = 0;
        $plannedInsideHorizon = 0;

        // The dominant cost of a preview: every date inside the horizon
        // is checked so the badge does not change as the student pages.
        // The instructor's windows, holidays and leave are the same for
        // all of them, so they are loaded once here.
        $this->batchedOver($withinHorizon, $instructorId, $durationMinutes, function () use ($withinHorizon, $instructorId, $studentId, $durationMinutes, $bufferMinutes, $isDemo, &$conflicts, &$bookable, &$plannedInsideHorizon): void {
            foreach ($withinHorizon as $occurrence) {
                $evaluated = $this->conflicts->evaluate(
                    $occurrence,
                    $instructorId,
                    $studentId,
                    $durationMinutes,
                    $bufferMinutes,
                    isDemo: $isDemo,
                );

                match (true) {
                    $evaluated->isConflict() => $conflicts++,
                    $evaluated->isBookable() => $bookable++,
                    // Inside the horizon by date, but still not
                    // reservable — a date at the very edge of the
                    // platform's advance window. Planned, not bookable
                    // and not a problem.
                    default => $plannedInsideHorizon++,
                };
            }
        });

        $total = $this->scheduler->totalOccurrences($rule, $skippedDates);
        $planned = $plannedInsideHorizon + ($total === null ? 0 : max(0, $total - count($withinHorizon)));

        return [$conflicts, $bookable, $planned];
    }

    /**
     * A horizon is at most `maximum_advance_booking_days` long, so even
     * a daily series cannot have more occurrences inside it than that
     * many days — plus headroom for a rule that produces several classes
     * on the same date in future variants.
     */
    private static function maxHorizonOccurrences(): int
    {
        return 400;
    }

    /**
     * Puts removed dates back into the RENDERED list, in date order,
     * without giving them a place in the schedule.
     *
     * Bounded to the span this page already covers, so paging still
     * shows each removed date exactly once, next to the classes it sits
     * between.
     *
     * @param  list<SeriesOccurrenceData>  $occurrences
     * @param  list<string>  $skippedDates
     * @return list<SeriesOccurrenceData>
     */
    private function withSkippedDatesShown(array $occurrences, array $skippedDates, int $durationMinutes, RecurrenceRuleData $rule): array
    {
        if ($skippedDates === [] || $occurrences === []) {
            return $occurrences;
        }

        $first = $occurrences[0]->localDate;
        $last = $occurrences[count($occurrences) - 1]->localDate;

        foreach ($skippedDates as $localDate) {
            if ($localDate < $first || $localDate > $last) {
                continue;
            }

            $localDateTime = $localDate.' '.$rule->timeOfDay;
            $startsAt = LocalWallClock::classify($localDateTime, $rule->timezone) === LocalWallClock::VALID
                ? CarbonImmutable::parse($localDateTime, $rule->timezone)->utc()
                : null;

            $occurrences[] = new SeriesOccurrenceData(
                sequence: 0,
                localDate: $localDate,
                localDateTime: $localDateTime,
                startsAt: $startsAt,
                endsAt: $startsAt?->addMinutes($durationMinutes),
                status: SeriesOccurrenceStatus::Skipped,
            );
        }

        usort(
            $occurrences,
            static fn (SeriesOccurrenceData $a, SeriesOccurrenceData $b): int => $a->localDate <=> $b->localDate,
        );

        return array_values($occurrences);
    }

    private function plannedOccurrence(RecurrenceOccurrenceData $occurrence, int $durationMinutes): SeriesOccurrenceData
    {
        return new SeriesOccurrenceData(
            sequence: $occurrence->sequence,
            localDate: $occurrence->localDate,
            localDateTime: $occurrence->localDateTime,
            startsAt: $occurrence->startsAt,
            endsAt: $occurrence->endsAt($durationMinutes),
            status: SeriesOccurrenceStatus::Planned,
        );
    }

    /**
     * Every occurrence of a FINITE rule, for checks that must consider
     * the schedule as a whole (representability, totals).
     *
     * @param  list<string>  $skippedDates
     * @return list<RecurrenceOccurrenceData>
     */
    public function occurrencesFor(RecurrenceRuleData $rule, array $skippedDates = [], array $timeOverrides = []): array
    {
        return $this->scheduler->occurrences(
            $rule,
            RecurrenceRuleData::MAX_ENUMERATED_CANDIDATES,
            null,
            $skippedDates,
            $timeOverrides,
        );
    }

    /**
     * The occurrences that fall inside the confirmation horizon — the
     * only ones an ONGOING schedule can be judged as a whole on, since
     * it has no end to enumerate to.
     *
     * @param  list<string>  $skippedDates
     * @return list<RecurrenceOccurrenceData>
     */
    public function occurrencesWithinHorizon(RecurrenceRuleData $rule, array $skippedDates = [], array $timeOverrides = []): array
    {
        $horizonDate = $this->horizonDate($rule);
        $within = [];

        foreach ($this->scheduler->occurrences($rule, self::maxHorizonOccurrences(), null, $skippedDates, $timeOverrides) as $occurrence) {
            if ($occurrence->localDate > $horizonDate) {
                break;
            }

            $within[] = $occurrence;
        }

        return $within;
    }

    // ── Creation ────────────────────────────────────────────────────────

    /**
     * Creates the schedule, then reserves every class inside the
     * confirmation horizon.
     *
     * The series row is committed FIRST, on its own. That ordering is
     * deliberate: if occurrence creation then fails halfway — a slot
     * taken in the last second, a queue worker dying — the schedule
     * still exists, the classes that were created are real and paid for
     * normally, and the remainder is generated by the ordinary
     * background pass. The alternative (one transaction around the
     * series and all its bookings) would roll back confirmed
     * reservations the student has already been shown.
     *
     * @throws BookingException when not one class could be reserved
     */
    public function create(CreateBookingSeriesData $data): RecurringBookingResult
    {
        $type = $this->types->requireActiveByKey($data->typeKey);

        // Before anything is persisted: refuse a schedule this
        // deployment cannot keep. Checked here rather than in each
        // caller so the wizard, the JSON API and any future caller are
        // all covered by the same rule.
        $this->assertConfirmable($data->rule, $data->skippedDates, $data->timeOverrides);

        $series = BookingSeries::create([
            'booking_type_id' => $type->id,
            'student_id' => $data->studentId,
            'instructor_id' => $data->instructorId,
            'status' => BookingSeriesStatus::Active,
            'frequency' => $data->rule->frequency,
            'repeat_interval' => $data->rule->interval,
            'weekdays' => array_map(static fn ($day): int => $day->value, $data->rule->effectiveWeekdays()),
            'start_date' => $data->rule->startDate,
            'time_of_day' => $data->rule->timeOfDay,
            'duration_minutes' => $data->durationMinutes,
            'timezone' => $data->rule->timezone,
            'student_timezone' => $data->studentTimezone,
            'end_condition' => $data->rule->endCondition,
            'end_date' => $data->rule->endDate,
            'occurrence_count' => $data->rule->occurrenceCount,
            'meta' => array_filter(
                $data->meta,
                static fn (mixed $value): bool => $value !== null,
            ),
            'notes' => $data->notes,
            'created_by' => $data->createdBy,
        ]);

        // Recorded BEFORE the first generation pass so a date the
        // student already dropped is never booked and then cancelled.
        foreach ($data->skippedDates as $localDate) {
            $this->recordException($series, $localDate, BookingSeriesException::ACTION_SKIPPED, null);
        }

        foreach ($data->timeOverrides as $localDate => $localTime) {
            BookingSeriesException::query()->updateOrCreate(
                ['booking_series_id' => $series->id, 'local_date' => $localDate],
                ['action' => BookingSeriesException::ACTION_MOVED, 'local_time' => $localTime],
            );
        }

        $outcome = $this->generate($series, $this->settings->recurring_generation_batch_size);

        if ($outcome['booked']->isEmpty()) {
            // Nothing could be reserved, so there is no schedule to keep:
            // leaving an active series behind would keep trying forever
            // and would show the student a schedule with no classes in it.
            $series->update(['status' => BookingSeriesStatus::Cancelled]);

            throw new BookingException(
                'None of the requested classes could be booked: '.implode(' ', $outcome['failures']),
            );
        }

        $series->refresh();

        return new RecurringBookingResult(
            groupId: $series->id,
            booked: $outcome['booked'],
            failures: $outcome['failures'],
            series: $series,
            plannedCount: $this->plannedRemainder($series),
        );
    }

    // ── Generation ──────────────────────────────────────────────────────

    /**
     * Creates up to $limit not-yet-created classes of $series that fall
     * inside the confirmation horizon.
     *
     * Safe to call repeatedly and concurrently — see the class docblock.
     * Returns the bookings created and the dates that could not be, so
     * the caller (wizard, job or command) can report rather than guess.
     *
     * @return array{booked: Collection<int, Booking>, failures: array<string, string>, exhausted: bool}
     */
    public function generate(BookingSeries $series, ?int $limit = null): array
    {
        $limit ??= $this->settings->recurring_generation_batch_size;
        $booked = new Collection;
        $failures = [];

        if (! $series->status->generatesOccurrences()) {
            return ['booked' => $booked, 'failures' => $failures, 'exhausted' => true];
        }

        $type = $this->types->requireActiveByKey($series->type->key);
        $rule = $series->rule();
        $horizonDate = $this->horizonDate($rule);
        $watermark = $series->generated_through_date?->toDateString();

        $candidates = $this->scheduler->occurrences(
            $rule,
            max(1, $limit),
            $watermark,
            $series->skippedDates(),
            $series->timeOverrides(),
        );

        // No candidate at all past the watermark means a finite series
        // has been fully laid down. An ongoing one always has a next
        // date, so it never reaches this.
        if ($candidates === [] && $rule->endCondition !== RecurrenceEndCondition::Never) {
            $series->update(['status' => BookingSeriesStatus::Completed, 'last_generated_at' => now()]);

            return ['booked' => $booked, 'failures' => $failures, 'exhausted' => true];
        }

        $lastHandled = null;
        $exhausted = true;

        foreach ($candidates as $occurrence) {
            if ($occurrence->localDate > $horizonDate) {
                // Everything after this is beyond the horizon too, so the
                // series is not finished — just not due yet.
                $exhausted = false;
                break;
            }

            $result = $this->generateOccurrence($series, $type->duration_minutes, $type->buffer_minutes, $occurrence, ! $type->is_paid);

            if ($result === false) {
                // Not due yet — leave the watermark where it is so this
                // date is reconsidered next time round.
                $exhausted = false;
                break;
            }

            if ($result instanceof Booking) {
                $booked->push($result);
            } elseif (is_string($result)) {
                $failures[$occurrence->localDate] = $result;
            }

            $lastHandled = $occurrence->localDate;
        }

        if ($lastHandled !== null) {
            // The watermark advances over decided dates — booked, already
            // present, or recorded as an exception — never over dates the
            // horizon stopped us reaching.
            $series->update([
                'generated_through_date' => $lastHandled,
                'last_generated_at' => now(),
                'generation_failures' => $failures === [] ? 0 : $series->generation_failures + count($failures),
            ]);
        }

        return ['booked' => $booked, 'failures' => $failures, 'exhausted' => $exhausted];
    }

    /**
     * One occurrence. Returns the Booking on success, a reason string
     * when the date could not be booked, null when it already existed
     * (the idempotent no-op), or false when it is not due yet.
     */
    private function generateOccurrence(
        BookingSeries $series,
        int $durationMinutes,
        int $bufferMinutes,
        RecurrenceOccurrenceData $occurrence,
        bool $isDemo,
    ): Booking|string|false|null {
        // Cheap pre-check for the overwhelmingly common retry case. The
        // unique index below is the actual guarantee — this only avoids
        // paying for a rolled-back transaction to learn the same thing.
        $existing = $series->bookings()
            ->where('series_occurrence_date', $occurrence->localDate)
            ->first();

        if ($existing !== null) {
            return null;
        }

        $evaluated = $this->conflicts->evaluate(
            $occurrence,
            $series->instructor_id,
            $series->student_id,
            $durationMinutes,
            $bufferMinutes,
            isDemo: $isDemo,
        );

        // Not reservable yet only because its date is still too far
        // ahead. Nothing to record and nothing to report — the watermark
        // must not move past it, so the next pass tries it again.
        if ($evaluated->status === SeriesOccurrenceStatus::Planned) {
            return false;
        }

        if ($evaluated->isConflict()) {
            // Recorded, not dropped: it stays visible in the student's
            // series view, and it stops a counted series from silently
            // delivering one class fewer.
            $this->recordException(
                $series,
                $occurrence->localDate,
                BookingSeriesException::ACTION_CONFLICT,
                $evaluated->reason,
                $occurrence->startsAt,
            );

            return $evaluated->reason ?? $evaluated->status->label();
        }

        try {
            return $this->bookings->request($this->occurrenceBookingData($series, $durationMinutes, $occurrence));
        } catch (QueryException $exception) {
            // A concurrent worker won the same occurrence. The unique
            // index did its job; this is a successful no-op, not an error.
            if ($this->isUniqueViolation($exception)) {
                return null;
            }

            throw $exception;
        } catch (BookingException $exception) {
            // Availability re-checked under the instructor lock and
            // refused after the preview said yes — a genuine race. Same
            // treatment as any other conflict: recorded, never silent.
            $this->recordException(
                $series,
                $occurrence->localDate,
                BookingSeriesException::ACTION_CONFLICT,
                $exception->getMessage(),
                $occurrence->startsAt,
            );

            return $exception->getMessage();
        }
    }

    private function occurrenceBookingData(BookingSeries $series, int $durationMinutes, RecurrenceOccurrenceData $occurrence): CreateBookingData
    {
        return new CreateBookingData(
            typeKey: $series->type->key,
            studentId: (int) $series->student_id,
            instructorId: (int) $series->instructor_id,
            startsAt: $occurrence->startsAt,
            durationMinutes: $durationMinutes,
            // The STUDENT's timezone, exactly as a single booking stores
            // it — booking-origin provenance, unrelated to the series'
            // own scheduling calendar.
            timezone: $series->student_timezone,
            notes: $series->notes,
            meta: [
                ...($series->meta ?? []),
                // Unchanged from the pre-series shape so every existing
                // reader, report and API consumer keeps working. It is
                // the series id, which is also RecurringBookingResult's
                // $groupId — the same value it has always been.
                'recurring_group' => $series->id,
            ],
            recurrenceFrequency: $series->frequency,
            bookingSeriesId: $series->id,
            seriesOccurrenceDate: $occurrence->localDate,
        );
    }

    /** MySQL 1062 / SQLSTATE 23000 — the (series, date) unique index. */
    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function recordException(BookingSeries $series, string $localDate, string $action, ?string $reason, ?CarbonImmutable $startsAt = null): void
    {
        $exception = BookingSeriesException::query()->updateOrCreate(
            ['booking_series_id' => $series->id, 'local_date' => $localDate],
            ['action' => $action, 'reason' => $reason],
        );

        if ($action !== BookingSeriesException::ACTION_CONFLICT) {
            return;
        }

        // Ids and a date only — never the student's or instructor's
        // identity, the notes, or anything about payment.
        Log::info('Recurring class occurrence could not be scheduled.', [
            'booking_series_id' => $series->id,
            'local_date' => $localDate,
        ]);

        // Tell the student ONCE, ever, for this date.
        //
        // The row is written with updateOrCreate, so "it exists" cannot
        // mean "already reported" — a repeated or retried pass touches it
        // again quite legitimately. The stamp is the durable claim, and
        // it is taken BEFORE dispatching so a crash between the two
        // leaves a silent date rather than a repeated message. Silence is
        // the safer failure: the date is still visible in the schedule,
        // and the alternative wakes a student at 3am for the same lost
        // class every hour.
        if ($exception->notified_at !== null) {
            return;
        }

        $exception->forceFill(['notified_at' => now()])->save();

        BookingSeriesOccurrenceUnavailable::dispatch($series, $localDate, $startsAt, $reason);
    }

    // ── Management ──────────────────────────────────────────────────────

    /**
     * Removes one date from the schedule.
     *
     * If the date is already a booking it is cancelled through the
     * ordinary engine — so refund policy, the timeline, events and
     * notifications behave exactly as they do for any other
     * cancellation. The exception row is what stops the background pass
     * from booking it again, and what keeps a counted series honest
     * about how many classes it still owes.
     *
     * @throws BookingException
     */
    public function skipOccurrence(BookingSeries $series, string $localDate, BookingActor $actor, ?string $reason = null): void
    {
        $this->assertSeriesActive($series);

        $booking = $series->bookings()->where('series_occurrence_date', $localDate)->first();

        if ($booking !== null && ! $booking->status->isTerminal()) {
            $this->bookings->cancel($booking, new CancelBookingData(
                $actor,
                $reason ?? 'Removed from the repeating schedule.',
            ));
        }

        $this->recordException($series, $localDate, BookingSeriesException::ACTION_SKIPPED, $reason);
    }

    /**
     * Moves ONE class of a series to a different time on the same date,
     * leaving the rest of the schedule alone.
     *
     * Recorded as a per-date deviation rather than by splitting the
     * series in two, so the student still has one schedule they can
     * extend, edit and cancel as a unit. A moved class keeps its place
     * in the sequence and still counts towards a counted series — it
     * happened, just not at the usual time.
     *
     * @param  string  $localTime  `H:i:s` in the SERIES' timezone
     *
     * @throws BookingException
     */
    public function moveOccurrence(BookingSeries $series, string $localDate, string $localTime, BookingActor $actor): void
    {
        $this->assertSeriesActive($series);

        BookingSeriesException::query()->updateOrCreate(
            ['booking_series_id' => $series->id, 'local_date' => $localDate],
            ['action' => BookingSeriesException::ACTION_MOVED, 'local_time' => $localTime, 'reason' => null],
        );

        // An already-created class is moved through the ordinary engine,
        // so the reschedule allowance, availability re-check under the
        // instructor lock, timeline and notifications all apply exactly
        // as they would for any other reschedule.
        $booking = $series->bookings()->where('series_occurrence_date', $localDate)->first();

        if ($booking !== null && ! $booking->status->isTerminal()) {
            $this->bookings->reschedule($booking, new RescheduleBookingData(
                startsAt: CarbonImmutable::parse($localDate.' '.$localTime, $series->timezone)->utc(),
                actor: $actor,
                reason: 'Moved within the repeating schedule.',
            ));
        }
    }

    /**
     * Tries a date that previously could not be booked, now that
     * whatever blocked it may be gone.
     *
     * A conflict exception is otherwise permanent, and permanently
     * excluding a date the instructor has since freed up would be the
     * schedule quietly losing a class for good. The ordinary sweep
     * cannot do this on its own: the date sits behind
     * `generated_through_date`, so it is never revisited — which is
     * exactly what makes the watermark cheap. So this addresses the one
     * date directly.
     *
     * Returns the booking when it worked. On failure the exception is
     * left in place (with a fresh reason) and null comes back, so the
     * student can try again later without the date vanishing.
     *
     * @throws BookingException
     */
    public function retryOccurrence(BookingSeries $series, string $localDate): ?Booking
    {
        $this->assertSeriesActive($series);

        $exception = $series->exceptions()
            ->where('local_date', $localDate)
            ->where('action', BookingSeriesException::ACTION_CONFLICT)
            ->first();

        if ($exception === null) {
            throw new BookingException('That date is not waiting to be rebooked.');
        }

        $type = $this->types->requireActiveByKey($series->type->key);

        // Rebuild the occurrence from the rule WITHOUT the exception in
        // the skip list, so the date exists again for this one attempt.
        $skipped = array_values(array_diff($series->skippedDates(), [$localDate]));

        $occurrence = collect($this->scheduler->occurrences(
            $series->rule(),
            1,
            CarbonImmutable::parse($localDate, $series->timezone)->subDay()->toDateString(),
            $skipped,
            $series->timeOverrides(),
        ))->first();

        if ($occurrence === null || $occurrence->localDate !== $localDate) {
            throw new BookingException('That date is no longer part of this schedule.');
        }

        // Clearing it first is what lets the ordinary path run unchanged;
        // a failure below records it again, so the date is never lost.
        $exception->delete();

        $result = $this->generateOccurrence(
            $series,
            (int) $type->duration_minutes,
            (int) $type->buffer_minutes,
            $occurrence,
            ! $type->is_paid,
        );

        if ($result instanceof Booking) {
            return $result;
        }

        // The retry failed and recorded the conflict again. The student
        // asked for this and is looking at the answer right now, so
        // suppress the notification — being emailed about a failure you
        // just triggered yourself is noise, not news.
        $series->exceptions()
            ->where('local_date', $localDate)
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        return null;
    }

    /**
     * Cancels part or all of a series.
     *
     * `ThisOnly` leaves the schedule intact and drops one date.
     * `ThisAndFollowing` truncates the RULE at that date, so nothing
     * later is ever generated — the series keeps its history rather
     * than being deleted. `RemainingSeries` does the same from the
     * first still-cancellable class onwards.
     *
     * Classes that have already happened, their payments, and any
     * per-class exceptions are untouched in every case: a series is
     * ended going forward, never rewritten backwards.
     *
     * @return int how many scheduled classes were cancelled
     *
     * @throws BookingException
     */
    public function cancelFrom(BookingSeries $series, Booking $from, SeriesChangeScope $scope, BookingActor $actor, ?string $reason = null): int
    {
        if ((string) $from->booking_series_id !== (string) $series->id) {
            throw new BookingException('That class is not part of this schedule.');
        }

        if ($scope === SeriesChangeScope::ThisOnly) {
            $this->skipOccurrence($series, $from->series_occurrence_date->toDateString(), $actor, $reason);

            return 1;
        }

        $boundary = $scope === SeriesChangeScope::ThisAndFollowing
            ? $from->starts_at
            : CarbonImmutable::now();

        $cancelled = 0;

        // Ordered and re-read per row: each cancellation runs through the
        // engine's own guards, and one refusal (a class that has already
        // started, say) must not abort the rest.
        $scheduled = $series->bookings()
            ->where('starts_at', '>=', $boundary)
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->orderBy('starts_at')
            ->get();

        foreach ($scheduled as $booking) {
            try {
                $this->bookings->cancel($booking, new CancelBookingData(
                    $actor,
                    $reason ?? 'The repeating schedule was cancelled.',
                ));
                $cancelled++;
            } catch (BookingException $exception) {
                Log::info('A class of a cancelled series could not be cancelled.', [
                    'booking_series_id' => $series->id,
                    'booking_id' => $booking->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        // Truncating the rule — rather than deleting the series — is what
        // preserves completed classes, payment history and the record of
        // what the student had agreed to.
        $series->update([
            'status' => BookingSeriesStatus::Cancelled,
            'end_condition' => RecurrenceEndCondition::OnDate,
            'end_date' => $boundary->setTimezone($series->timezone)->subDay()->toDateString(),
            'occurrence_count' => null,
        ]);

        return $cancelled;
    }

    /**
     * Lengthens a finite series in place.
     *
     * Nothing existing is recreated: the rule's end moves and the
     * ordinary generation pass fills the new dates in. Bookings already
     * made keep their ids, references, payments and meetings, so an
     * extension can never disturb a class the student has already paid
     * for.
     *
     * @param  int|null  $additionalClasses  for a count-bounded series
     * @param  string|null  $newEndDate  `Y-m-d` in the series timezone, for a date-bounded one
     *
     * @throws BookingException
     */
    public function extend(BookingSeries $series, ?int $additionalClasses = null, ?string $newEndDate = null): BookingSeries
    {
        if ($series->status === BookingSeriesStatus::Cancelled) {
            throw new BookingException('This schedule has been cancelled and cannot be extended.');
        }

        if ($series->isOngoing()) {
            throw new BookingException('This schedule already continues until you cancel it.');
        }

        if ($series->end_condition === RecurrenceEndCondition::AfterCount) {
            if ($additionalClasses === null || $additionalClasses < 1) {
                throw new BookingException('Choose how many more classes to add.');
            }

            $series->occurrence_count = (int) $series->occurrence_count + $additionalClasses;
        } else {
            if ($newEndDate === null || $newEndDate <= $series->end_date->toDateString()) {
                throw new BookingException('Choose a new end date after the current one.');
            }

            $series->end_date = $newEndDate;
        }

        // A completed series becomes active again: it now has dates ahead
        // of it, and the watermark means generation resumes exactly where
        // it stopped rather than re-walking the whole schedule.
        $series->status = BookingSeriesStatus::Active;
        $series->save();

        return $series->refresh();
    }

    /**
     * The schedule as it currently stands, mixing classes that already
     * exist with dates the rule still owes.
     *
     * A booking is the authority wherever one exists — including a
     * rescheduled one, whose real time is shown rather than the rule's —
     * because the booking is the reservation and the rule is only the
     * intent behind it.
     */
    public function scheduleFor(BookingSeries $series, int $page = 1, int $perPage = self::PREVIEW_PAGE_SIZE): SeriesSchedulePreviewData
    {
        $rule = $series->rule();
        $horizonDate = $this->horizonDate($rule);
        $skipped = $series->skippedDates();
        $overrides = $series->timeOverrides();
        $duration = (int) $series->duration_minutes;

        $offset = max(0, ($page - 1) * $perPage);
        $window = $this->scheduler->occurrences($rule, $offset + $perPage + 1, null, $skipped, $overrides);
        $hasMore = count($window) > $offset + $perPage;

        $existing = $series->bookings()
            ->get()
            ->keyBy(fn (Booking $booking): string => $booking->series_occurrence_date?->toDateString() ?? '');

        $occurrences = [];

        foreach (array_slice($window, $offset, $perPage) as $occurrence) {
            $booking = $existing->get($occurrence->localDate);

            if ($booking !== null) {
                $occurrences[] = new SeriesOccurrenceData(
                    sequence: $occurrence->sequence,
                    localDate: $occurrence->localDate,
                    localDateTime: $occurrence->localDateTime,
                    startsAt: $booking->starts_at,
                    endsAt: $booking->ends_at,
                    status: match ($booking->status) {
                        BookingStatus::Cancelled => SeriesOccurrenceStatus::Cancelled,
                        BookingStatus::Pending => SeriesOccurrenceStatus::Reserved,
                        default => SeriesOccurrenceStatus::Confirmed,
                    },
                    bookingId: $booking->id,
                    bookingReference: $booking->reference,
                );

                continue;
            }

            $occurrences[] = $this->plannedOccurrence($occurrence, $duration);
        }

        // Dates that are no longer part of the rule still have to be
        // VISIBLE here: a removed date the student may want back, and —
        // more importantly — a date the platform could not book, which
        // they can ask us to try again.
        $occurrences = $this->withDeviationsShown($series, $occurrences, $duration);

        $total = $this->scheduler->totalOccurrences($rule, $skipped);

        return new SeriesSchedulePreviewData(
            occurrences: $occurrences,
            totalScheduled: $total,
            conflictCount: 0,
            bookableNowCount: $existing->count(),
            plannedCount: $this->plannedRemainder($series) ?? 0,
            horizonDays: $this->horizonDays(),
            lastLocalDate: $this->scheduler->lastLocalDate($rule, $skipped),
            hasMore: $hasMore,
            instructorId: (int) $series->instructor_id,
            timezone: $series->timezone,
        );
    }

    /**
     * Merges the series' own exception rows back into a rendered
     * schedule, bounded to the span the page already covers.
     *
     * @param  list<SeriesOccurrenceData>  $occurrences
     * @return list<SeriesOccurrenceData>
     */
    private function withDeviationsShown(BookingSeries $series, array $occurrences, int $durationMinutes): array
    {
        if ($occurrences === []) {
            return $occurrences;
        }

        $first = $occurrences[0]->localDate;
        $last = $occurrences[count($occurrences) - 1]->localDate;

        $rows = $series->exceptions()
            ->whereIn('action', BookingSeriesException::REMOVING_ACTIONS)
            ->get();

        foreach ($rows as $row) {
            $localDate = $row->local_date->toDateString();

            if ($localDate < $first || $localDate > $last) {
                continue;
            }

            $localDateTime = $localDate.' '.$series->rule()->timeOfDay;
            $startsAt = LocalWallClock::classify($localDateTime, $series->timezone) === LocalWallClock::VALID
                ? CarbonImmutable::parse($localDateTime, $series->timezone)->utc()
                : null;

            $occurrences[] = new SeriesOccurrenceData(
                sequence: 0,
                localDate: $localDate,
                localDateTime: $localDateTime,
                startsAt: $startsAt,
                endsAt: $startsAt?->addMinutes($durationMinutes),
                status: $row->action === BookingSeriesException::ACTION_CONFLICT
                    ? SeriesOccurrenceStatus::InstructorUnavailable
                    : SeriesOccurrenceStatus::Skipped,
                reason: $row->reason,
            );
        }

        usort(
            $occurrences,
            static fn (SeriesOccurrenceData $a, SeriesOccurrenceData $b): int => $a->localDate <=> $b->localDate,
        );

        return array_values($occurrences);
    }

    /**
     * Runs $work with the instructor's static availability loaded once
     * for the span these occurrences cover.
     *
     * A no-op when nothing is representable — there is no span to load,
     * and asking for one would mean inventing bounds.
     *
     * @param  list<RecurrenceOccurrenceData>  $occurrences
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    private function batchedOver(array $occurrences, int $instructorId, int $durationMinutes, Closure $work): mixed
    {
        $instants = array_values(array_filter(array_map(
            static fn (RecurrenceOccurrenceData $occurrence): ?CarbonImmutable => $occurrence->startsAt,
            $occurrences,
        )));

        if ($instants === []) {
            return $work();
        }

        return $this->conflicts->batched(
            $instructorId,
            min($instants)->subDay(),
            max($instants)->addMinutes($durationMinutes)->addDay(),
            $work,
        );
    }

    /** @throws BookingException */
    private function assertSeriesActive(BookingSeries $series): void
    {
        if ($series->status === BookingSeriesStatus::Cancelled) {
            throw new BookingException('This repeating schedule has been cancelled.');
        }
    }

    /**
     * Classes of a finite series that are scheduled but not yet
     * reserved. Null for an ongoing series — there is no remainder to
     * count, and inventing one would imply an end that does not exist.
     */
    public function plannedRemainder(BookingSeries $series): ?int
    {
        $total = $this->scheduler->totalOccurrences($series->rule(), $series->skippedDates());

        if ($total === null) {
            return null;
        }

        return max(0, $total - $series->bookings()->count());
    }
}
