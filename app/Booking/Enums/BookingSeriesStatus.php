<?php

declare(strict_types=1);

namespace App\Booking\Enums;

enum BookingSeriesStatus: string
{
    /** Still generating / still has classes ahead of it. */
    case Active = 'active';

    /** A finite series whose last occurrence has been generated. */
    case Completed = 'completed';

    /** Ended early by the student; no further occurrences are generated. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function generatesOccurrences(): bool
    {
        return $this === self::Active;
    }
}
