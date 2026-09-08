<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date the student removed from a series.
 *
 * Recorded rather than inferred: "no booking exists for this date" is
 * ambiguous (not generated yet, generation failed, or deliberately
 * dropped), and only the deliberate case must survive regeneration and
 * be excluded from a counted series' total.
 */
class BookingSeriesException extends Model
{
    use HasFactory, HasUuids;

    /** The student removed the date themselves. */
    public const string ACTION_SKIPPED = 'skipped';

    /**
     * The date became unbookable after the series was created (the
     * instructor took leave, the slot was taken). Recorded rather than
     * dropped so it stays visible and so a counted series reaches one
     * date further instead of quietly delivering one class fewer.
     */
    public const string ACTION_CONFLICT = 'conflict';

    /**
     * The class still happens on this date, at $local_time rather than
     * the rule's shared time. Counts towards a counted series — it is a
     * moved class, not a lost one.
     */
    public const string ACTION_MOVED = 'moved';

    /** Deviations that REMOVE the date from the schedule. */
    public const array REMOVING_ACTIONS = [self::ACTION_SKIPPED, self::ACTION_CONFLICT];

    protected $fillable = [
        'booking_series_id',
        'local_date',
        'local_time',
        'action',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'booking_series_id');
    }
}
