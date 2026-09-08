<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * How a recurring series stops.
 *
 * The three cases are deliberately distinct rather than one nullable
 * "end" field, because they answer different questions and produce
 * different student-facing copy, different pricing displays, and
 * different generation behaviour:
 *
 *  - OnDate     — the calendar decides. Dates that turn out to be
 *                 unavailable are simply not booked, so the class COUNT
 *                 falls; the last possible date never moves.
 *  - AfterCount — the count decides. An unavailable date is skipped and
 *                 the series reaches further into the calendar to still
 *                 deliver the requested number of classes.
 *  - Never      — ongoing. There is no last date and therefore no total
 *                 price; classes are confirmed rolling-forward inside
 *                 the confirmation horizon.
 */
enum RecurrenceEndCondition: string
{
    case OnDate = 'on_date';
    case AfterCount = 'after_count';
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::OnDate => 'On a date',
            self::AfterCount => 'After a number of classes',
            self::Never => 'Keep going until I cancel',
        };
    }

    /** Whether the series has a knowable, finite last occurrence. */
    public function isFinite(): bool
    {
        return $this !== self::Never;
    }
}
