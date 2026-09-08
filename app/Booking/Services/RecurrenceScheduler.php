<?php

declare(strict_types=1);

namespace App\Booking\Services;

use App\Booking\DTOs\RecurrenceOccurrenceData;
use App\Booking\DTOs\RecurrenceRuleData;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use App\Support\Timezone\LocalWallClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;

/**
 * The single place a recurrence rule becomes dates.
 *
 * Pure: no persistence, no clock, no availability. Everything that
 * needs to know when a series' classes fall — the wizard preview, the
 * review step, initial creation, the rolling background generation, the
 * API — asks this class, so the client can never disagree with the
 * server about what "every other Tuesday and Thursday" means.
 *
 * Two properties are worth stating outright:
 *
 *  - Dates are enumerated in the RULE's timezone, never UTC and never
 *    the viewer's. Weekday membership, the every-N-weeks phase and the
 *    end date are all statements about that one calendar; deriving them
 *    from a UTC instant puts an instructor in Sydney a day out.
 *  - The intended wall clock is preserved across daylight saving. Each
 *    occurrence pairs the calendar date (which date arithmetic gets
 *    right) with the rule's own time of day, and that pairing — not a
 *    fixed UTC offset from the first class — is what resolves to an
 *    instant. A reading that a spring-forward transition deletes is
 *    reported as unrepresentable rather than silently moved.
 */
final class RecurrenceScheduler
{
    /**
     * Occurrences of $rule, in chronological order.
     *
     * @param  int  $limit  how many to return (a page, not a series cap)
     * @param  string|null  $afterLocalDate  `Y-m-d`; return only dates strictly after it.
     *                                       Earlier dates still consume their sequence number, so paging never
     *                                       renumbers a series.
     * @param  list<string>  $skippedDates  `Y-m-d` dates the student removed. They are
     *                                      neither returned nor counted — which is what makes a
     *                                      "12 classes" series still deliver 12 when one date is dropped.
     * @param  array<string, string>  $timeOverrides  `Y-m-d => H:i:s` in the rule's
     *                                                timezone, for individual classes the student moved. A moved class
     *                                                is still a class, so it keeps its place and its sequence number.
     * @return list<RecurrenceOccurrenceData>
     */
    public function occurrences(
        RecurrenceRuleData $rule,
        int $limit,
        ?string $afterLocalDate = null,
        array $skippedDates = [],
        array $timeOverrides = [],
    ): array {
        if ($limit < 1) {
            return [];
        }

        $skipped = array_flip($skippedDates);
        $results = [];

        // Resuming does not re-walk the schedule from the beginning. How
        // many classes precede the watermark is ARITHMETIC — see
        // countCandidatesUpTo() — so generating class 9,000 of a long
        // series costs the same as generating class 9, and the
        // enumeration guard bounds this PAGE rather than the position in
        // the schedule. Walking instead would have turned the guard into
        // a silent cap: everything past it would simply stop being
        // generated, with no error and no missing-class report.
        $sequence = $afterLocalDate === null
            ? 0
            : max(0, $this->countCandidatesUpTo($rule, $afterLocalDate) - $this->countSkippedUpTo($skippedDates, $afterLocalDate));

        $from = $afterLocalDate === null
            ? null
            : CarbonImmutable::parse($afterLocalDate, $rule->timezone)->addDay()->toDateString();

        foreach ($this->candidateDates($rule, $from) as $localDate) {
            if (isset($skipped[$localDate])) {
                continue;
            }

            $sequence++;
            $results[] = $this->occurrenceAt($rule, $sequence, $localDate, $timeOverrides[$localDate] ?? null);

            if (count($results) >= $limit) {
                break;
            }

            if ($rule->endCondition === RecurrenceEndCondition::AfterCount && $sequence >= (int) $rule->occurrenceCount) {
                break;
            }
        }

        return $results;
    }

    /**
     * How many classes a FINITE series schedules in total, or null for an
     * ongoing one — which has no total, and must never be shown as if it
     * had (there is no price to quote for "until I cancel").
     *
     * @param  list<string>  $skippedDates
     */
    public function totalOccurrences(RecurrenceRuleData $rule, array $skippedDates = []): ?int
    {
        if (! $rule->endCondition->isFinite()) {
            return null;
        }

        if ($rule->endCondition === RecurrenceEndCondition::AfterCount) {
            return (int) $rule->occurrenceCount;
        }

        // Arithmetic, so a long schedule's total is exact rather than
        // whatever the walk managed before the enumeration guard stopped
        // it.
        return max(0, $this->countCandidatesUpTo($rule, (string) $rule->endDate)
            - $this->countSkippedUpTo($skippedDates, (string) $rule->endDate));
    }

    /**
     * The last date a finite series can reach, or null when it is
     * ongoing or when the rule produces nothing at all.
     *
     * @param  list<string>  $skippedDates
     */
    public function lastLocalDate(RecurrenceRuleData $rule, array $skippedDates = []): ?string
    {
        if (! $rule->endCondition->isFinite()) {
            return null;
        }

        $total = $this->totalOccurrences($rule, $skippedDates);

        if ($total === null || $total < 1) {
            return null;
        }

        if ($rule->endCondition === RecurrenceEndCondition::OnDate) {
            // The last DATE the rule reaches is fixed by the end date;
            // the last CLASS is the last of those the student did not
            // remove, so step back over any skipped tail.
            $index = $this->countCandidatesUpTo($rule, (string) $rule->endDate);

            while ($index >= 1) {
                $candidate = $this->nthCandidate($rule, $index);

                if ($candidate === null) {
                    return null;
                }

                if (! in_array($candidate, $skippedDates, true)) {
                    return $candidate;
                }

                $index--;
            }

            return null;
        }

        // "After N classes": the N-th class is the N-th candidate plus
        // however many removed dates fall on or before it — the schedule
        // reaches further so the student still gets N. Removals are few,
        // so this settles in a couple of passes.
        $index = $total;

        for ($pass = 0; $pass <= count($skippedDates) + 1; $pass++) {
            $candidate = $this->nthCandidate($rule, $index);

            if ($candidate === null) {
                return null;
            }

            $effective = $index - $this->countSkippedUpTo($skippedDates, $candidate);

            if ($effective === $total && ! in_array($candidate, $skippedDates, true)) {
                return $candidate;
            }

            $index += max(1, $total - $effective);
        }

        return null;
    }

    /**
     * Whether $localDate is a date this rule schedules at all (ignoring
     * skips). Answered by asking the generator for the first candidate
     * on or after that date, so it costs the same however far into a
     * long schedule the date sits.
     */
    public function matchesDate(RecurrenceRuleData $rule, string $localDate): bool
    {
        foreach ($this->candidateDates($rule, $localDate) as $candidate) {
            return $candidate === $localDate;
        }

        return false;
    }

    /**
     * How many dates this rule schedules on or before $localDate,
     * ignoring skips — computed, never walked.
     *
     * This is what lets a long schedule resume cheaply and, more
     * importantly, resume CORRECTLY: the alternative (walk from the
     * start counting) makes the enumeration guard a silent ceiling, past
     * which classes quietly stop being generated.
     */
    public function countCandidatesUpTo(RecurrenceRuleData $rule, string $localDate): int
    {
        $start = $rule->startLocalDate();
        $end = $rule->endLocalDate();
        $upTo = CarbonImmutable::parse($localDate, $rule->timezone)->startOfDay();

        // An end date bounds the schedule regardless of how far the
        // question reaches past it.
        if ($end !== null && $upTo->greaterThan($end)) {
            $upTo = $end;
        }

        if ($upTo->lessThan($start)) {
            return 0;
        }

        if ($rule->frequency === RecurrenceFrequency::Daily) {
            return intdiv((int) $start->diffInDays($upTo), $rule->interval) + 1;
        }

        $weekdays = array_map(static fn (Weekday $day): int => $day->value, $rule->effectiveWeekdays());
        $anchorWeek = $start->startOfWeek(CarbonInterface::SUNDAY);
        $weeksBetween = intdiv((int) $anchorWeek->diffInDays($upTo->startOfWeek(CarbonInterface::SUNDAY)), 7);

        // The start's own week is partial: days before the start date are
        // not scheduled. Every later on-cycle week is whole.
        $firstWeekDays = count(array_filter(
            $weekdays,
            static fn (int $day): bool => ! $anchorWeek->addDays($day)->lessThan($start),
        ));

        // On-cycle week offsets are 0, interval, 2*interval, … — this is
        // how many of them fall strictly before the week $upTo lands in.
        $onCycleWeeksBefore = (int) ceil($weeksBetween / $rule->interval);

        $total = $onCycleWeeksBefore >= 1
            ? $firstWeekDays + (($onCycleWeeksBefore - 1) * count($weekdays))
            : 0;

        // Plus the part of $upTo's own week that has already happened,
        // but only if that week is one the schedule repeats in.
        if ($weeksBetween % $rule->interval === 0) {
            $week = $anchorWeek->addWeeks($weeksBetween);

            foreach ($weekdays as $day) {
                $date = $week->addDays($day);

                if (! $date->lessThan($start) && ! $date->greaterThan($upTo)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    /** @param  list<string>  $skippedDates */
    private function countSkippedUpTo(array $skippedDates, string $localDate): int
    {
        return count(array_filter($skippedDates, static fn (string $date): bool => $date <= $localDate));
    }

    /**
     * The $n-th date the rule schedules (1-based), ignoring skips, or
     * null when it lies beyond any date worth representing.
     *
     * Computed rather than walked, for the same reason as
     * countCandidatesUpTo(): a schedule's length must not decide whether
     * its end can be worked out.
     */
    public function nthCandidate(RecurrenceRuleData $rule, int $n): ?string
    {
        if ($n < 1) {
            return null;
        }

        $start = $rule->startLocalDate();

        if ($rule->frequency === RecurrenceFrequency::Daily) {
            return $this->boundedDate($start, ($n - 1) * $rule->interval);
        }

        $weekdays = array_map(static fn (Weekday $day): int => $day->value, $rule->effectiveWeekdays());
        $anchorWeek = $start->startOfWeek(CarbonInterface::SUNDAY);

        $firstWeek = array_values(array_filter(
            $weekdays,
            static fn (int $day): bool => ! $anchorWeek->addDays($day)->lessThan($start),
        ));

        if ($n <= count($firstWeek)) {
            return $anchorWeek->addDays($firstWeek[$n - 1])->toDateString();
        }

        $remaining = $n - count($firstWeek);
        $cycle = intdiv($remaining - 1, count($weekdays)) + 1;
        $dayIndex = ($remaining - 1) % count($weekdays);

        return $this->boundedDate($anchorWeek, ($cycle * $rule->interval * 7) + $weekdays[$dayIndex]);
    }

    /**
     * $base plus $days, or null when that is further out than a calendar
     * date can meaningfully express.
     *
     * A schedule length is not capped, but a request for a hundred
     * million classes has no last date worth quoting — and reporting
     * "unknown" is honest, where a silently wrapped or crashed date is
     * not.
     */
    private function boundedDate(CarbonImmutable $base, int $days): ?string
    {
        if ($days < 0 || $days > 3_650_000) {
            return null;
        }

        return $base->addDays($days)->toDateString();
    }

    private function occurrenceAt(RecurrenceRuleData $rule, int $sequence, string $localDate, ?string $timeOverride = null): RecurrenceOccurrenceData
    {
        // The reading the series MEANS, reconstructed from the calendar
        // date plus the rule's own time of day. Inspecting a materialised
        // Carbon instance instead could never reveal a skipped reading —
        // PHP normalises 01:30 to 02:30 the moment it is constructed.
        $localDateTime = $localDate.' '.($timeOverride ?? $rule->timeOfDay);
        $classification = LocalWallClock::classify($localDateTime, $rule->timezone);

        return new RecurrenceOccurrenceData(
            sequence: $sequence,
            localDate: $localDate,
            localDateTime: $localDateTime,
            timezone: $rule->timezone,
            startsAt: $classification === LocalWallClock::VALID
                ? CarbonImmutable::parse($localDateTime, $rule->timezone)->utc()
                : null,
            wallClock: $classification,
        );
    }

    /**
     * Every date the rule schedules from $fromLocalDate onwards,
     * chronologically, ignoring skips and the class count.
     *
     * $fromLocalDate is a real fast-forward, not a filter: the generator
     * jumps straight to the right step or repeat week, so resuming a long
     * schedule never re-walks the part already behind it. That is what
     * keeps MAX_ENUMERATED_CANDIDATES a bound on ONE PAGE rather than a
     * silent ceiling on how many classes a schedule may ever contain —
     * walking from the start would have made everything past the guard
     * quietly stop being generated, with no error and no missing-class
     * report.
     *
     * @return Generator<int, string> `Y-m-d` in the rule's timezone
     */
    private function candidateDates(RecurrenceRuleData $rule, ?string $fromLocalDate = null): Generator
    {
        $start = $rule->startLocalDate();
        $end = $rule->endLocalDate();
        $from = $fromLocalDate === null
            ? $start
            : CarbonImmutable::parse($fromLocalDate, $rule->timezone)->startOfDay();

        if ($from->lessThan($start)) {
            $from = $start;
        }

        $emitted = 0;

        if ($rule->frequency === RecurrenceFrequency::Daily) {
            $steps = (int) ceil(((int) $start->diffInDays($from)) / $rule->interval);
            $date = $start->addDays(max(0, $steps) * $rule->interval);

            for (; $end === null || $date->lessThanOrEqualTo($end); $date = $date->addDays($rule->interval)) {
                if (++$emitted > RecurrenceRuleData::MAX_ENUMERATED_CANDIDATES) {
                    return;
                }

                yield $date->toDateString();
            }

            return;
        }

        // Weeks are anchored on the Sunday of the start date's own week,
        // so `Weekday`'s Sunday-first numbering is also chronological
        // order within a week and "every N weeks" keeps its phase across
        // month and year boundaries (week arithmetic, never day-of-month).
        //
        // This anchor is INTERNAL, and deliberately not changed by the
        // calendar now being drawn Monday-first: moving it would shift
        // which weeks an existing every-N-weeks series falls in, which
        // means moving real classes somebody already agreed to.
        $weekdays = $rule->effectiveWeekdays();
        $anchorWeek = $start->startOfWeek(CarbonInterface::SUNDAY);

        // Jump to the first repeat week at or after the one $from is in.
        $weeksBetween = intdiv((int) $anchorWeek->diffInDays($from->startOfWeek(CarbonInterface::SUNDAY)), 7);
        $cycles = (int) ceil($weeksBetween / $rule->interval);
        $week = $anchorWeek->addWeeks($cycles * $rule->interval);

        for (; ; $week = $week->addWeeks($rule->interval)) {
            foreach ($weekdays as $weekday) {
                /** @var Weekday $weekday */
                $date = $week->addDays($weekday->value);

                if ($date->lessThan($from)) {
                    continue;
                }

                if ($end !== null && $date->greaterThan($end)) {
                    return;
                }

                if (++$emitted > RecurrenceRuleData::MAX_ENUMERATED_CANDIDATES) {
                    return;
                }

                yield $date->toDateString();
            }

            // An end date before the next repeat week: nothing further can
            // match, so stop rather than walking to the guard.
            if ($end !== null && $week->addWeeks($rule->interval)->greaterThan($end)) {
                return;
            }
        }
    }
}
