<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use InvalidArgumentException;

/**
 * What the STUDENT chose, before it is anchored to a calendar.
 *
 * Deliberately distinct from RecurrenceRuleData. A student picks
 * "Mondays and Thursdays, every 2 weeks, 40 classes" in their own
 * timezone and against a slot they selected; a rule is that intent
 * resolved against the instructor's scheduling calendar, with a
 * concrete start date, wall clock and timezone. Keeping the two apart
 * is what lets the server own the anchoring — the browser never
 * decides which calendar a weekday belongs to, and never sends a
 * timezone the server then trusts.
 *
 * $weekdays are in the STUDENT's timezone, because that is the
 * calendar the checkboxes were rendered in; WizardBookingService
 * translates them.
 */
final readonly class RecurrencePatternData
{
    /** Repeat-every-N. Bounded because a value beyond this is a typo, not a schedule. */
    public const int MAX_INTERVAL = 12;

    /** @var list<Weekday> */
    public array $weekdays;

    /** @param list<Weekday> $weekdays */
    public function __construct(
        public RecurrenceFrequency $frequency,
        public int $interval = 1,
        array $weekdays = [],
        public RecurrenceEndCondition $endCondition = RecurrenceEndCondition::AfterCount,
        /** `Y-m-d` in the STUDENT's timezone. */
        public ?string $endDate = null,
        public ?int $occurrenceCount = null,
    ) {
        if ($interval < 1 || $interval > self::MAX_INTERVAL) {
            throw new InvalidArgumentException('Repeat interval is out of range.');
        }

        $unique = [];

        foreach ($weekdays as $weekday) {
            $unique[$weekday->value] = $weekday;
        }

        ksort($unique);

        $this->weekdays = array_values($unique);
    }
}
