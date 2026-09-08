<?php

declare(strict_types=1);

namespace App\Models;

use App\Booking\DTOs\RecurrenceRuleData;
use App\Booking\Enums\BookingSeriesStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A repeating class schedule: the RULE, plus the state needed to keep
 * turning it into individual class bookings over time.
 *
 * The distinction that matters throughout this model is rule vs.
 * reservation. This row says what the student asked for ("every Monday
 * and Wednesday at 18:00 London time, 40 classes"); the `bookings`
 * rows are the classes actually reserved, priced and paid for. Only the
 * latter are commitments — which is why an ongoing series is
 * representable at all, and why it has no total price.
 *
 * The rule's timezone is this row's own, not the student's and not the
 * server's. See the `timezone` column comment in the creating migration.
 */
class BookingSeries extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'booking_series';

    protected $fillable = [
        'booking_type_id',
        'student_id',
        'instructor_id',
        'status',
        'frequency',
        'repeat_interval',
        'weekdays',
        'start_date',
        'time_of_day',
        'duration_minutes',
        'timezone',
        'student_timezone',
        'end_condition',
        'end_date',
        'occurrence_count',
        'generated_through_date',
        'last_generated_at',
        'generation_failures',
        'meta',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingSeriesStatus::class,
            'frequency' => RecurrenceFrequency::class,
            'end_condition' => RecurrenceEndCondition::class,
            'weekdays' => 'array',
            'meta' => 'array',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'generated_through_date' => 'immutable_date',
            'last_generated_at' => 'immutable_datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BookingType::class, 'booking_type_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'booking_series_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(BookingSeriesException::class, 'booking_series_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BookingSeriesStatus::Active);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * The rule, in the one shape the scheduler understands.
     *
     * Rebuilt from columns on every call rather than cached, so an
     * extension or edit persisted a moment ago is always what the next
     * generation pass schedules from.
     */
    public function rule(): RecurrenceRuleData
    {
        return new RecurrenceRuleData(
            frequency: $this->frequency,
            startDate: $this->start_date->toDateString(),
            timeOfDay: $this->normalizedTimeOfDay(),
            timezone: $this->timezone,
            interval: (int) $this->repeat_interval,
            weekdays: array_map(
                static fn (int|string $day): Weekday => Weekday::from((int) $day),
                $this->weekdays ?? [],
            ),
            endCondition: $this->end_condition,
            endDate: $this->end_date?->toDateString(),
            occurrenceCount: $this->occurrence_count === null ? null : (int) $this->occurrence_count,
        );
    }

    /**
     * Dates that are NOT part of the schedule any more — removed by the
     * student, or found unbookable after the fact. A moved class is
     * deliberately absent from this list: it still happens, just at
     * another time, so it must keep counting towards a counted series.
     *
     * @return list<string> `Y-m-d`
     */
    public function skippedDates(): array
    {
        return $this->exceptions()
            ->whereIn('action', BookingSeriesException::REMOVING_ACTIONS)
            ->pluck('local_date')
            ->map(static fn ($date): string => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
            ->values()
            ->all();
    }

    /**
     * Classes of this series that still stand — the number the student
     * is actually committed to. Cancelled occurrences are excluded
     * precisely because a cancelled class is a class that will not
     * happen, and a counted series must reach further to replace it.
     */
    public function liveBookingsCount(): int
    {
        return $this->bookings()
            ->whereNotIn('status', [BookingStatus::Cancelled])
            ->count();
    }

    /**
     * Per-date time overrides, `Y-m-d => H:i:s` in the series timezone.
     *
     * @return array<string, string>
     */
    public function timeOverrides(): array
    {
        return $this->exceptions()
            ->where('action', BookingSeriesException::ACTION_MOVED)
            ->whereNotNull('local_time')
            ->get()
            ->mapWithKeys(static fn (BookingSeriesException $exception): array => [
                $exception->local_date->toDateString() => (string) $exception->local_time,
            ])
            ->all();
    }

    /**
     * The stored rule in one readable phrase, in the SERIES' own terms.
     *
     * Reads whatever is on the row, including shapes the booking form no
     * longer offers. The form dropped the Daily/Weekly switch and the
     * "every N weeks" control in favour of picking days directly, but
     * series created under the old form still hold a real interval or a
     * Daily frequency and are still generated from it — so this has to
     * be able to say "Every 2 weeks on Tuesday", or the student would
     * see their own schedule described as something it is not.
     *
     * Nothing is ever converted. A stored rule is what the student
     * agreed to; rewriting it to fit a simpler form would change real,
     * future classes.
     */
    public function describe(): string
    {
        $days = array_map(
            static fn (int|string $day): string => Weekday::from((int) $day)->label(),
            $this->weekdays ?? [],
        );

        $interval = max(1, (int) $this->repeat_interval);

        if ($this->frequency === RecurrenceFrequency::Daily) {
            return $interval === 1 ? 'Every day' : sprintf('Every %d days', $interval);
        }

        if ($days === []) {
            return $interval === 1 ? 'Weekly' : sprintf('Every %d weeks', $interval);
        }

        $readable = count($days) === 1
            ? $days[0]
            : implode(', ', array_slice($days, 0, -1)).' and '.end($days);

        if (count($days) === 7 && $interval === 1) {
            return 'Every day';
        }

        return $interval === 1
            ? 'Every '.$readable
            : sprintf('Every %d weeks on %s', $interval, $readable);
    }

    public function isOngoing(): bool
    {
        return $this->end_condition === RecurrenceEndCondition::Never;
    }

    /**
     * MySQL returns TIME as `H:i:s`, but a value written as `H:i` round
     * trips unchanged on some connections — normalised so the rule's
     * wall clock is always seconds-precise.
     */
    private function normalizedTimeOfDay(): string
    {
        $value = (string) $this->time_of_day;

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value.':00' : $value;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'frequency', 'repeat_interval', 'weekdays', 'start_date', 'end_date', 'occurrence_count', 'end_condition'])
            ->useLogName('booking_series')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
