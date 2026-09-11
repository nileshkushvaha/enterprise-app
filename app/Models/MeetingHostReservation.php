<?php

declare(strict_types=1);

namespace App\Models;

use App\Booking\Enums\MeetingHostReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One booking's claim on one host's capacity over its occupied UTC
 * interval. See the migration and MeetingHostCapacityService for the
 * rules; this class is deliberately thin.
 */
class MeetingHostReservation extends Model
{
    use HasUuids;

    protected $fillable = [
        'platform_meeting_host_id',
        'booking_id',
        'provider',
        'lesson_starts_at',
        'lesson_ends_at',
        'occupies_from',
        'occupies_until',
        'status',
        'expires_at',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingHostReservationStatus::class,
            'lesson_starts_at' => 'immutable_datetime',
            'lesson_ends_at' => 'immutable_datetime',
            'occupies_from' => 'immutable_datetime',
            'occupies_until' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(PlatformMeetingHost::class, 'platform_meeting_host_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MeetingHostReservationStatus::Active);
    }
}
