<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The complete, validated description of a repeating class schedule.
 *
 * This is the ONE shape the whole system agrees on: the wizard builds
 * it, the API builds it, `booking_series` stores it, and
 * RecurrenceScheduler is the only thing that turns it into dates. There
 * is deliberately no second, client-side notion of what a rule means —
 * the browser only ever renders dates the server produced from this.
 *
 * Everything here is expressed in `$timezone`, the series' own
 * recurrence calendar. That is stored explicitly rather than inferred,
 * because "every Monday at 18:00" is a statement about a particular
 * clock, and which clock it is has to survive a profile change, a
 * daylight-saving transition, and a year boundary.
 */
final readonly class RecurrenceRuleData
{
    /**
     * Enumeration guard. Not a cap on how many classes a student may
     * book — it bounds a single generation pass over the calendar so a
     * pathological rule (or an ongoing series asked for "everything")
     * can never spin. Roughly 20 years of daily classes.
     */
    public const int MAX_ENUMERATED_CANDIDATES = 7500;

    /** @var list<Weekday> */
    public array $weekdays;

    /**
     * @param  list<Weekday>  $weekdays  Weekly only: which days repeat. Empty means
     *                                   "the start date's own weekday" — a plain weekly series.
     * @param  int  $interval  Repeat every N days (Daily) or N weeks (Weekly).
     * @param  string  $timeOfDay  `H:i:s` local to $timezone — one shared class time.
     * @param  string  $startDate  `Y-m-d` local to $timezone.
     */
    public function __construct(
        public RecurrenceFrequency $frequency,
        public string $startDate,
        public string $timeOfDay,
        public string $timezone,
        public int $interval = 1,
        array $weekdays = [],
        public RecurrenceEndCondition $endCondition = RecurrenceEndCondition::AfterCount,
        public ?string $endDate = null,
        public ?int $occurrenceCount = null,
    ) {
        if ($interval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be at least 1.');
        }

        if ($endCondition === RecurrenceEndCondition::AfterCount && ($occurrenceCount === null || $occurrenceCount < 1)) {
            throw new InvalidArgumentException('A class count is required when the series ends after a number of classes.');
        }

        if ($endCondition === RecurrenceEndCondition::OnDate) {
            if ($endDate === null) {
                throw new InvalidArgumentException('An end date is required when the series ends on a date.');
            }

            if ($endDate < $startDate) {
                throw new InvalidArgumentException('The end date cannot be before the start date.');
            }
        }

        // Deduplicated and ordered so two rules that mean the same thing
        // compare, render and store identically.
        $unique = [];

        foreach ($weekdays as $weekday) {
            $unique[$weekday->value] = $weekday;
        }

        ksort($unique);

        $this->weekdays = array_values($unique);
    }

    /** The weekdays that actually repeat — falling back to the start date's own day. */
    public function effectiveWeekdays(): array
    {
        if ($this->frequency !== RecurrenceFrequency::Weekly) {
            return [];
        }

        if ($this->weekdays !== []) {
            return $this->weekdays;
        }

        return [Weekday::from((int) $this->startLocalDate()->dayOfWeek)];
    }

    public function startLocalDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->startDate, $this->timezone)->startOfDay();
    }

    public function endLocalDate(): ?CarbonImmutable
    {
        return $this->endDate === null
            ? null
            : CarbonImmutable::parse($this->endDate, $this->timezone)->startOfDay();
    }

    /** @return array<string, mixed> the storable form; see BookingSeries */
    public function toArray(): array
    {
        return [
            'frequency' => $this->frequency->value,
            'interval' => $this->interval,
            'weekdays' => array_map(static fn (Weekday $day): int => $day->value, $this->weekdays),
            'start_date' => $this->startDate,
            'time_of_day' => $this->timeOfDay,
            'timezone' => $this->timezone,
            'end_condition' => $this->endCondition->value,
            'end_date' => $this->endDate,
            'occurrence_count' => $this->occurrenceCount,
        ];
    }

    /** @param array<string, mixed> $rule */
    public static function fromArray(array $rule): self
    {
        return new self(
            frequency: RecurrenceFrequency::from((string) $rule['frequency']),
            startDate: (string) $rule['start_date'],
            timeOfDay: (string) $rule['time_of_day'],
            timezone: (string) $rule['timezone'],
            interval: (int) ($rule['interval'] ?? 1),
            weekdays: array_map(static fn (int|string $day): Weekday => Weekday::from((int) $day), $rule['weekdays'] ?? []),
            endCondition: RecurrenceEndCondition::from((string) $rule['end_condition']),
            endDate: $rule['end_date'] ?? null,
            occurrenceCount: isset($rule['occurrence_count']) ? (int) $rule['occurrence_count'] : null,
        );
    }
}
