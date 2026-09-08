<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use Carbon\CarbonImmutable;

/**
 * The simple "N occurrences spaced by whole days or whole weeks" shape.
 *
 * Kept as the compatibility surface for callers — the student JSON API
 * and existing integrations — that describe a series this way. It is a
 * strictly narrower way of saying the same thing RecurrenceRuleData
 * says, so `toRule()` is the only place it is ever interpreted, and
 * every path ends up scheduled by the one RecurrenceScheduler.
 *
 * The former `MAX_OCCURRENCES = 12` constant is gone rather than
 * raised. A schedule's length is now a property of the rule, and how
 * much of it is reserved at any moment is bounded by the confirmation
 * horizon (BookingSeriesService) — a system limit, not a limit on what
 * a student may ask for. Reintroducing a student-facing number here
 * would put the cap straight back.
 */
final readonly class RecurrenceData
{
    public function __construct(
        public int $occurrences,
        public RecurrenceFrequency $frequency = RecurrenceFrequency::Weekly,
        public int $interval = 1,
    ) {}

    public function nextStartsAt(CarbonImmutable $first, int $index): CarbonImmutable
    {
        return match ($this->frequency) {
            RecurrenceFrequency::Daily => $first->addDays($index * $this->interval),
            RecurrenceFrequency::Weekly => $first->addWeeks($index * $this->interval),
        };
    }

    /**
     * The full rule this shorthand means.
     *
     * $anchor is the first class, already expressed in the recurrence
     * timezone — its date becomes the start date, its wall clock the
     * shared class time, and (for a weekly cadence) its weekday the one
     * repeating day. That is exactly what this shape has always meant;
     * stating it as a rule is what lets it be stored, extended and
     * generated like any other series.
     */
    public function toRule(CarbonImmutable $anchor, string $timezone): RecurrenceRuleData
    {
        $local = $anchor->setTimezone($timezone);

        return new RecurrenceRuleData(
            frequency: $this->frequency,
            startDate: $local->toDateString(),
            timeOfDay: $local->format('H:i:s'),
            timezone: $timezone,
            interval: $this->interval,
            weekdays: $this->frequency === RecurrenceFrequency::Weekly
                ? [Weekday::from((int) $local->dayOfWeek)]
                : [],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: max(1, $this->occurrences),
        );
    }
}
