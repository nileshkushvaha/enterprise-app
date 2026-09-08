<?php

declare(strict_types=1);

namespace App\Booking\Enums;

/** Which classes of a series an edit or cancellation applies to. */
enum SeriesChangeScope: string
{
    case ThisOnly = 'this_only';
    case ThisAndFollowing = 'this_and_following';
    case RemainingSeries = 'remaining_series';

    public function label(): string
    {
        return match ($this) {
            self::ThisOnly => 'This class only',
            self::ThisAndFollowing => 'This and all following classes',
            self::RemainingSeries => 'All remaining classes in the series',
        };
    }
}
