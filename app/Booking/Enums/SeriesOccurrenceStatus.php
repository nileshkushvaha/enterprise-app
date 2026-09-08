<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/**
 * What is true about one date of a recurring schedule, right now.
 *
 * The three "not yet a booking" cases are kept apart on purpose. A
 * student needs to know the difference between "this date is fine, we
 * just have not reserved it yet" (Planned) and "this date does not
 * work" (a conflict) — collapsing them is how a schedule quietly loses
 * classes nobody agreed to lose.
 */
enum SeriesOccurrenceStatus: string
{
    /** A real booking that is confirmed and going ahead. */
    case Confirmed = 'confirmed';

    /**
     * A real booking whose slot is held but which is not confirmed yet
     * — a paid class awaiting payment. Kept distinct from Confirmed
     * because "confirmed" next to an unpaid class tells the student
     * their place is secure when it is only being held.
     */
    case Reserved = 'reserved';

    /**
     * Checked against real availability and bookable now — but NOT yet
     * booked. It becomes Reserved when the slot is held and Confirmed
     * only once the reservation and any payment have actually
     * succeeded. The three are deliberately distinct words.
     */
    case Available = 'available';

    /** Inside the rule, beyond the confirmation horizon — not checked or held yet. */
    case Planned = 'planned';

    /** The student removed this date. */
    case Skipped = 'skipped';

    /** The instructor is not available (window, leave, holiday, another class, daily cap). */
    case InstructorUnavailable = 'instructor_unavailable';

    /** The student already has a class at this time. */
    case StudentBusy = 'student_busy';

    /** Daylight saving deletes or duplicates this wall-clock reading. */
    case UnrepresentableTime = 'unrepresentable_time';

    /** Outside the bookable window (too soon, or beyond the platform's advance limit). */
    case OutsideBookingWindow = 'outside_booking_window';

    /** This occurrence's booking was cancelled. */
    case Cancelled = 'cancelled';

    public function isConflict(): bool
    {
        return match ($this) {
            self::InstructorUnavailable,
            self::StudentBusy,
            self::UnrepresentableTime,
            self::OutsideBookingWindow => true,
            default => false,
        };
    }

    /** Whether this occurrence should become a booking when the series is confirmed. */
    public function isBookable(): bool
    {
        return $this === self::Available;
    }

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Reserved => 'Reserved — payment due',
            self::Available => 'Available to book',
            self::Planned => 'Planned',
            self::Skipped => 'Skipped',
            self::InstructorUnavailable => 'Instructor unavailable',
            self::StudentBusy => 'You already have a class',
            self::UnrepresentableTime => 'Time does not exist',
            self::OutsideBookingWindow => 'Outside the booking window',
            self::Cancelled => 'Cancelled',
        };
    }
}
