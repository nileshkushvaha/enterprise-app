<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\AvailabilityServiceInterface;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Holiday;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\TeacherUnavailability;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The read cache that makes a schedule preview affordable.
 *
 * A cache over availability is only acceptable if it cannot change an
 * answer, so these tests are almost entirely about EQUIVALENCE: for
 * every date, and across holidays, leave, unpublished instructors and
 * existing bookings, the cached pass must reach exactly the verdict the
 * uncached one does.
 *
 * The one thing deliberately NOT cached is bookings, because the
 * generation path creates them as it goes and each occurrence has to
 * see the one before it.
 */
class AvailabilityReadCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private BookingType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        UserProfile::updateOrCreate(['user_id' => $this->teacher->id], [
            'instructor_status' => 'approved',
            'profile_visibility' => 'public',
            'timezone' => 'UTC',
        ]);
        TeacherSubject::factory()->state(['teacher_id' => $this->teacher->id])->subject('maths', 1, 12)->create();

        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()
                ->state(['teacher_id' => $this->teacher->id])
                ->forDay($day)
                ->between('09:00:00', '17:00:00')
                ->create();
        }

        $this->type = BookingType::factory()->create([
            'key' => 'paid_one_to_one', 'is_paid' => true, 'is_active' => true, 'duration_minutes' => 60,
        ]);

        $settings = app(BookingSettings::class);
        $settings->maximum_advance_booking_days = 3650;
        $settings->max_daily_bookings_per_teacher = null;
        $settings->save();
    }

    /** @return list<CarbonImmutable> a fortnight of candidate starts */
    private function candidates(): array
    {
        $first = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);
        $dates = [];

        for ($i = 0; $i < 14; $i++) {
            $dates[] = $first->addDays($i);
        }

        return $dates;
    }

    /**
     * @param  list<CarbonImmutable>  $candidates
     * @return array<string, bool> ISO start => is available
     */
    private function verdicts(array $candidates, bool $cached): array
    {
        $availability = app(AvailabilityServiceInterface::class);

        $check = function () use ($candidates, $availability): array {
            $out = [];

            foreach ($candidates as $start) {
                try {
                    $availability->ensureAvailable($this->teacher->id, $start, $start->addHour());
                    $out[$start->toIso8601String()] = true;
                } catch (SlotUnavailableException) {
                    $out[$start->toIso8601String()] = false;
                }
            }

            return $out;
        };

        if (! $cached) {
            return $check();
        }

        return $availability->withCachedReads(
            $this->teacher->id,
            $candidates[0]->subDay(),
            end($candidates)->addDays(2),
            $check,
        );
    }

    private function assertCachedMatchesUncached(string $scenario): void
    {
        $candidates = $this->candidates();

        $this->assertSame(
            $this->verdicts($candidates, cached: false),
            $this->verdicts($candidates, cached: true),
            "cached and uncached verdicts diverged: {$scenario}",
        );
    }

    // ── Equivalence ────────────────────────────────────────────────────────

    public function test_verdicts_match_with_plain_availability(): void
    {
        $this->assertCachedMatchesUncached('plain availability');
    }

    public function test_verdicts_match_across_a_holiday(): void
    {
        Holiday::query()->create([
            'name' => 'Test Holiday',
            'date' => $this->candidates()[3]->toDateString(),
        ]);

        $this->assertCachedMatchesUncached('holiday');

        // And the holiday genuinely blocks that date, so the match above
        // is not two identical all-true answers.
        $verdicts = $this->verdicts($this->candidates(), cached: true);
        $this->assertFalse($verdicts[$this->candidates()[3]->toIso8601String()]);
    }

    public function test_verdicts_match_across_instructor_leave(): void
    {
        $blocked = $this->candidates()[5];

        TeacherUnavailability::query()->create([
            'teacher_id' => $this->teacher->id,
            'starts_at' => $blocked->startOfDay(),
            'ends_at' => $blocked->endOfDay(),
            'timezone' => 'UTC',
            'reason' => 'Leave',
        ]);

        $this->assertCachedMatchesUncached('leave');

        $verdicts = $this->verdicts($this->candidates(), cached: true);
        $this->assertFalse($verdicts[$blocked->toIso8601String()]);
    }

    public function test_verdicts_match_when_a_window_does_not_cover_the_time(): void
    {
        TeacherAvailability::query()->where('teacher_id', $this->teacher->id)->delete();

        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()
                ->state(['teacher_id' => $this->teacher->id])
                ->forDay($day)
                ->between('14:00:00', '17:00:00')
                ->create();
        }

        // 10:00 is now outside every window — every date must refuse.
        $verdicts = $this->verdicts($this->candidates(), cached: true);

        $this->assertNotContains(true, $verdicts);
        $this->assertCachedMatchesUncached('narrow windows');
    }

    public function test_verdicts_match_when_the_instructor_is_not_bookable(): void
    {
        UserProfile::where('user_id', $this->teacher->id)->update(['instructor_status' => 'pending']);

        $verdicts = $this->verdicts($this->candidates(), cached: true);

        $this->assertNotContains(true, $verdicts);
        $this->assertCachedMatchesUncached('unapproved instructor');
    }

    public function test_verdicts_match_around_an_existing_booking(): void
    {
        $taken = $this->candidates()[7];

        Booking::factory()->create([
            'instructor_id' => $this->teacher->id,
            'booking_type_id' => $this->type->id,
            'status' => BookingStatus::Confirmed,
            'starts_at' => $taken,
            'ends_at' => $taken->addHour(),
        ]);

        $this->assertCachedMatchesUncached('existing booking');

        $verdicts = $this->verdicts($this->candidates(), cached: true);
        $this->assertFalse($verdicts[$taken->toIso8601String()]);
    }

    // ── The thing that must NOT be cached ──────────────────────────────────

    public function test_a_booking_created_during_the_pass_is_seen_immediately(): void
    {
        // Generation books occurrences one after another through a single
        // service instance. If bookings were cached, the second would not
        // see the first and the instructor would be double-booked.
        $candidates = $this->candidates();
        $target = $candidates[2];
        $availability = app(AvailabilityServiceInterface::class);

        $availability->withCachedReads($this->teacher->id, $candidates[0]->subDay(), end($candidates)->addDays(2), function () use ($availability, $target): void {
            $availability->ensureAvailable($this->teacher->id, $target, $target->addHour());

            Booking::factory()->create([
                'instructor_id' => $this->teacher->id,
                'booking_type_id' => $this->type->id,
                'status' => BookingStatus::Confirmed,
                'starts_at' => $target,
                'ends_at' => $target->addHour(),
            ]);

            $this->expectException(SlotUnavailableException::class);
            $availability->ensureAvailable($this->teacher->id, $target, $target->addHour());
        });
    }

    public function test_the_cache_is_released_even_when_the_work_throws(): void
    {
        $candidates = $this->candidates();
        $availability = app(AvailabilityServiceInterface::class);

        try {
            $availability->withCachedReads($this->teacher->id, $candidates[0], end($candidates), function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // A later question must hit the database again, so a change made
        // after the failed pass is visible.
        UserProfile::where('user_id', $this->teacher->id)->update(['instructor_status' => 'pending']);

        $this->expectException(SlotUnavailableException::class);
        $availability->ensureAvailable($this->teacher->id, $candidates[0], $candidates[0]->addHour());
    }

    public function test_a_question_outside_the_cached_range_is_still_asked_live(): void
    {
        $candidates = $this->candidates();
        $availability = app(AvailabilityServiceInterface::class);
        $outside = end($candidates)->addDays(30);

        Holiday::query()->create(['name' => 'Far Holiday', 'date' => $outside->toDateString()]);

        $availability->withCachedReads($this->teacher->id, $candidates[0], end($candidates), function () use ($availability, $outside): void {
            // Outside the preloaded window, so the holiday must still be
            // found rather than assumed absent.
            $this->expectException(SlotUnavailableException::class);
            $availability->ensureAvailable($this->teacher->id, $outside, $outside->addHour());
        });
    }

    // ── The point of the exercise ──────────────────────────────────────────

    public function test_the_cached_pass_costs_far_fewer_queries(): void
    {
        $candidates = $this->candidates();

        DB::enableQueryLog();
        $this->verdicts($candidates, cached: false);
        $uncached = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->verdicts($candidates, cached: true);
        $cached = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            $uncached / 2,
            $cached,
            sprintf('expected the cached pass to at least halve the queries (uncached %d, cached %d)', $uncached, $cached),
        );
    }
}
