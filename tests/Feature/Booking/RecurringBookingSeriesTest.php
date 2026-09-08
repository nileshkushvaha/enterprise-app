<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\WizardBookingServiceInterface;
use App\Booking\DTOs\RecurrencePatternData;
use App\Booking\DTOs\WizardBookingData;
use App\Booking\Enums\BookingActor;
use App\Booking\Enums\BookingSeriesStatus;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\SeriesChangeScope;
use App\Booking\Enums\SeriesOccurrenceStatus;
use App\Booking\Enums\Weekday;
use App\Booking\Services\BookingSeriesService;
use App\Booking\Services\RecurrenceScheduler;
use App\Jobs\Booking\GenerateBookingSeriesOccurrences;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\BookingSeriesException;
use App\Models\BookingType;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\TeacherUnavailability;
use App\Models\User;
use App\Models\UserProfile;
use App\Settings\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesAcademicBookingContext;
use Tests\Support\CreatesStudentLessonPrices;
use Tests\TestCase;

/**
 * Recurring classes as a stored SCHEDULE rather than N loose bookings.
 *
 * The behaviours pinned here are the ones the old twelve-occurrence
 * design could not express at all: a schedule longer than the calendar
 * we hold at once, a schedule with no end, a schedule that survives
 * being generated twice, and one whose dates can be changed without
 * recreating the classes already paid for.
 */
class RecurringBookingSeriesTest extends TestCase
{
    use CreatesAcademicBookingContext;
    use CreatesStudentLessonPrices;
    use RefreshDatabase;

    private User $teacher;

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
            TeacherAvailability::factory()->state(['teacher_id' => $this->teacher->id])->forDay($day)->between('09:00:00', '17:00:00')->create();
        }

        BookingType::query()->firstOrCreate(['key' => 'free_demo'], ['name' => 'Free Demo', 'duration_minutes' => 30, 'is_active' => true]);

        // A long horizon and no per-day cap, so these tests exercise the
        // recurrence rules themselves rather than the platform's window.
        // Future generation is switched ON here because that is what
        // these tests are about; the flag's own behaviour is covered by
        // RecurringScheduleReleaseSafeguardsTest.
        $settings = app(BookingSettings::class);
        $settings->maximum_advance_booking_days = 3650;
        $settings->max_daily_bookings_per_teacher = null;
        $settings->recurring_future_generation_enabled = true;
        $settings->save();
    }

    private function student(): User
    {
        return User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
    }

    /** A student who can actually be charged for a paid lesson. */
    private function payingStudent(): User
    {
        $priced = $this->createPaidBookingTypeWithPrice('paid_one_to_one', 499.00, 'INR', durationMinutes: 60);
        $student = $this->student();
        $this->assignBillingCountry($student, $priced['country']);
        $this->actingAs($student);

        return $student;
    }

    /** The next $weekday at 10:00 UTC, always comfortably in the future. */
    private function nextWeekdayAt(Weekday $weekday, int $hour = 10): CarbonImmutable
    {
        $date = CarbonImmutable::now('UTC')->addDays(3)->setTime($hour, 0)->startOfHour();

        while ((int) $date->dayOfWeek !== $weekday->value) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function wizardData(CarbonImmutable $startsAt, string $timezone = 'UTC'): WizardBookingData
    {
        return new WizardBookingData(
            typeKey: 'paid_one_to_one',
            subject: 'maths',
            grade: 5,
            startsAt: $startsAt,
            timezone: $timezone,
            teacherId: $this->teacher->id,
        );
    }

    private function wizard(): WizardBookingServiceInterface
    {
        return app(WizardBookingServiceInterface::class);
    }

    /** @return list<string> every booked date, in order */
    private function bookedDates(BookingSeries $series): array
    {
        return $series->bookings()
            ->orderBy('starts_at')
            ->pluck('series_occurrence_date')
            ->map(static fn ($date): string => $date->format('Y-m-d'))
            ->all();
    }

    // ── Shape of a schedule ────────────────────────────────────────────────

    public function test_a_daily_schedule_books_consecutive_days(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Daily,
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 5,
            ),
        );

        $this->assertNotNull($result->series);
        $this->assertSame([
            $start->toDateString(),
            $start->addDay()->toDateString(),
            $start->addDays(2)->toDateString(),
            $start->addDays(3)->toDateString(),
            $start->addDays(4)->toDateString(),
        ], $this->bookedDates($result->series));
    }

    public function test_a_weekly_schedule_can_repeat_on_several_weekdays(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday, Weekday::Wednesday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
        );

        $this->assertSame([
            $start->toDateString(),
            $start->addDays(2)->toDateString(),
            $start->addDays(7)->toDateString(),
            $start->addDays(9)->toDateString(),
        ], $this->bookedDates($result->series));

        // One shared class time: every class starts at the same wall clock.
        $this->assertSame(
            ['10:00'],
            $result->series->bookings()->get()->map(fn (Booking $b): string => $b->starts_at->format('H:i'))->unique()->values()->all(),
        );
    }

    public function test_every_n_weeks_skips_the_weeks_in_between(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Tuesday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                interval: 2,
                weekdays: [Weekday::Tuesday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $this->assertSame([
            $start->toDateString(),
            $start->addWeeks(2)->toDateString(),
            $start->addWeeks(4)->toDateString(),
        ], $this->bookedDates($result->series));
    }

    public function test_an_end_date_bounds_the_schedule_inclusively(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $lastDate = $start->addWeeks(2)->toDateString();

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::OnDate,
                endDate: $lastDate,
            ),
        );

        $this->assertSame([
            $start->toDateString(),
            $start->addWeek()->toDateString(),
            $lastDate,
        ], $this->bookedDates($result->series));
        $this->assertSame($lastDate, $result->series->end_date->toDateString());
    }

    // ── No twelve-class limit ──────────────────────────────────────────────

    public function test_a_schedule_of_more_than_twelve_classes_is_accepted_in_full(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                // Well past the old cap, and past the confirmation
                // horizon, so this exercises BOTH halves: the whole
                // schedule is accepted, and only part of it is reserved.
                occurrenceCount: 40,
            ),
        );

        $series = $result->series;

        $this->assertSame(40, (int) $series->occurrence_count);
        $this->assertGreaterThan(12, $series->bookings()->count() + (int) $result->plannedCount);
        $this->assertSame(
            40,
            $series->bookings()->count() + (int) $result->plannedCount,
            'every class is either booked now or planned — none are lost',
        );
    }

    public function test_a_long_schedule_reserves_only_as_far_as_the_confirmation_horizon(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $horizonDays = app(BookingSeriesService::class)->horizonDays();

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 40,
            ),
        );

        $horizonDate = $start->addDays($horizonDays)->toDateString();

        foreach ($this->bookedDates($result->series) as $date) {
            $this->assertLessThanOrEqual($horizonDate, $date, 'nothing is reserved beyond the horizon');
        }

        $this->assertGreaterThan(0, $result->plannedCount, 'the remainder is planned, not dropped');
    }

    public function test_an_ongoing_schedule_has_no_total_and_keeps_generating(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::Never,
            ),
        );

        $series = $result->series;

        $this->assertTrue($series->isOngoing());
        // No total, ever. An ongoing schedule has no last class, so any
        // total (and any price built from one) would be invented.
        $this->assertNull($result->plannedCount);
        $this->assertNull(app(BookingSeriesService::class)->plannedRemainder($series));
        $this->assertGreaterThan(0, $series->bookings()->count());
        $this->assertSame(BookingSeriesStatus::Active, $series->refresh()->status);
    }

    // ── Idempotence ────────────────────────────────────────────────────────

    public function test_generating_the_same_schedule_twice_creates_no_duplicate_classes(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 40,
            ),
        );

        $series = $result->series->refresh();
        $before = $this->bookedDates($series);

        // A retried job, a duplicated queue message, a second worker.
        app(BookingSeriesService::class)->generate($series);
        app(BookingSeriesService::class)->generate($series->refresh());
        GenerateBookingSeriesOccurrences::dispatchSync($series->id);

        $this->assertSame($before, $this->bookedDates($series->refresh()));
    }

    public function test_a_reset_watermark_still_cannot_double_book_an_occurrence(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 5,
            ),
        );

        $series = $result->series->refresh();
        $before = $this->bookedDates($series);

        // The watermark is an optimisation; correctness comes from the
        // (series, occurrence date) unique index. Clearing it forces the
        // generator to re-walk every date it has already decided.
        $series->update(['generated_through_date' => null]);
        app(BookingSeriesService::class)->generate($series->refresh(), 50);

        $this->assertSame($before, $this->bookedDates($series->refresh()));
    }

    public function test_a_cancelled_class_is_never_regenerated(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
        );

        $series = $result->series->refresh();
        $second = $series->bookings()->orderBy('starts_at')->get()[1];
        $second->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

        $series->update(['generated_through_date' => null]);
        app(BookingSeriesService::class)->generate($series->refresh(), 50);

        $this->assertSame(
            1,
            $series->bookings()->where('series_occurrence_date', $second->series_occurrence_date)->count(),
            'the cancelled date keeps exactly one (cancelled) row',
        );
    }

    // ── Conflicts ──────────────────────────────────────────────────────────

    public function test_a_date_the_instructor_is_away_is_reported_before_confirmation(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $clashing = $start->addWeek();

        TeacherUnavailability::query()->create([
            'teacher_id' => $this->teacher->id,
            'starts_at' => $clashing->startOfDay(),
            'ends_at' => $clashing->endOfDay(),
            'timezone' => 'UTC',
            'reason' => 'Leave',
        ]);

        $preview = $this->wizard()->previewSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $this->assertSame(1, $preview->conflictCount);

        $conflict = $preview->conflicts()[0];
        $this->assertSame($clashing->toDateString(), $conflict->localDate);
        $this->assertSame(SeriesOccurrenceStatus::InstructorUnavailable, $conflict->status);
        $this->assertNotNull($conflict->reason);
    }

    public function test_an_unavailable_date_is_recorded_rather_than_silently_skipped(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $clashing = $start->addWeek();

        TeacherUnavailability::query()->create([
            'teacher_id' => $this->teacher->id,
            'starts_at' => $clashing->startOfDay(),
            'ends_at' => $clashing->endOfDay(),
            'timezone' => 'UTC',
            'reason' => 'Leave',
        ]);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $series = $result->series->refresh();

        $this->assertDatabaseHas('booking_series_exceptions', [
            'booking_series_id' => $series->id,
            'local_date' => $clashing->toDateString(),
            'action' => BookingSeriesException::ACTION_CONFLICT,
        ]);
        $this->assertNotContains($clashing->toDateString(), $this->bookedDates($series));
    }

    public function test_a_date_that_is_merely_too_far_ahead_is_planned_not_a_conflict(): void
    {
        // The two ways of failing the bookable window are different
        // things. A date beyond the platform's advance limit will become
        // bookable on its own, simply by time passing — reporting it as a
        // conflict would sit an unresolvable warning between dates that
        // are fine, and block confirmation for no reason.
        $settings = app(BookingSettings::class);
        $settings->maximum_advance_booking_days = 20;
        $settings->save();

        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $preview = $this->wizard()->previewSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 6,
            ),
        );

        $this->assertSame(0, $preview->conflictCount, 'a distant date is not a conflict');
        $this->assertGreaterThan(0, $preview->bookableNowCount);
        $this->assertGreaterThan(0, $preview->plannedCount);

        $statuses = array_map(
            static fn ($occurrence) => $occurrence->status,
            $preview->occurrences,
        );

        $this->assertNotContains(SeriesOccurrenceStatus::OutsideBookingWindow, $statuses);
        $this->assertContains(SeriesOccurrenceStatus::Planned, $statuses);
    }

    public function test_a_class_that_is_not_due_yet_leaves_the_watermark_alone(): void
    {
        $settings = app(BookingSettings::class);
        $settings->maximum_advance_booking_days = 20;
        $settings->save();

        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 6,
            ),
        );

        $series = $result->series->refresh();
        $booked = $this->bookedDates($series);

        // Nothing past the last booked date was decided, so nothing was
        // recorded as unbookable and the watermark stops there — the next
        // pass reconsiders those dates rather than skipping them.
        $this->assertNotEmpty($booked);
        $this->assertSame(end($booked), $series->generated_through_date->toDateString());
        $this->assertDatabaseCount('booking_series_exceptions', 0);
        $this->assertSame([], $result->failures);
    }

    public function test_a_counted_schedule_reaches_further_when_a_date_is_removed(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $skipped = $start->addWeek();

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
            skippedDates: [$skipped->toDateString()],
        );

        // Three classes were asked for and three are delivered: the
        // schedule runs one Monday further instead of losing one.
        $this->assertSame([
            $start->toDateString(),
            $start->addWeeks(2)->toDateString(),
            $start->addWeeks(3)->toDateString(),
        ], $this->bookedDates($result->series));
    }

    public function test_a_date_bounded_schedule_simply_has_one_class_fewer_when_a_date_is_removed(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $skipped = $start->addWeek();

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::OnDate,
                endDate: $start->addWeeks(2)->toDateString(),
            ),
            skippedDates: [$skipped->toDateString()],
        );

        $this->assertSame([
            $start->toDateString(),
            $start->addWeeks(2)->toDateString(),
        ], $this->bookedDates($result->series));
    }

    public function test_a_moved_class_keeps_its_place_and_its_date(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $moved = $start->addWeek();

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
            timeOverrides: [$moved->toDateString() => '14:00:00'],
        );

        $series = $result->series->refresh();
        $booking = $series->bookings()->where('series_occurrence_date', $moved->toDateString())->firstOrFail();

        $this->assertSame('14:00', $booking->starts_at->format('H:i'));
        // A moved class is still a class: three were asked for, three exist.
        $this->assertCount(3, $this->bookedDates($series));
    }

    // ── Instructor stability ───────────────────────────────────────────────

    public function test_every_class_in_a_schedule_belongs_to_the_same_instructor(): void
    {
        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        UserProfile::updateOrCreate(['user_id' => $other->id], [
            'instructor_status' => 'approved',
            'profile_visibility' => 'public',
            'timezone' => 'UTC',
        ]);
        TeacherSubject::factory()->state(['teacher_id' => $other->id])->subject('maths', 1, 12)->create();
        foreach (Weekday::cases() as $day) {
            TeacherAvailability::factory()->state(['teacher_id' => $other->id])->forDay($day)->between('09:00:00', '17:00:00')->create();
        }

        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
        );

        $this->assertSame(
            [$this->teacher->id],
            $result->series->bookings()->pluck('instructor_id')->unique()->values()->all(),
        );
    }

    // ── Timezone / DST ─────────────────────────────────────────────────────

    public function test_a_schedule_stores_its_own_timezone_explicitly(): void
    {
        // The scheduling calendar comes from the instructor's published
        // availability windows (AvailabilityRepository::calendarTimezoneFor),
        // not from their profile — so that is what this moves.
        UserProfile::where('user_id', $this->teacher->id)->update(['timezone' => 'Europe/London']);
        TeacherAvailability::query()->where('teacher_id', $this->teacher->id)->update(['timezone' => 'Europe/London']);
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start, 'Asia/Kolkata'),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 2,
            ),
        );

        $series = $result->series;

        // The scheduling calendar and the student's display calendar are
        // both recorded, and they are not the same thing.
        $this->assertSame('Europe/London', $series->timezone);
        $this->assertSame('Asia/Kolkata', $series->student_timezone);
        $this->assertSame('Asia/Kolkata', $series->bookings()->first()->timezone);
    }

    // ── Managing a schedule ────────────────────────────────────────────────

    public function test_cancelling_one_class_leaves_the_rest_of_the_schedule_alone(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
        );

        $series = $result->series->refresh();
        $second = $series->bookings()->orderBy('starts_at')->get()[1];

        app(BookingSeriesService::class)->cancelFrom(
            $series,
            $second,
            SeriesChangeScope::ThisOnly,
            BookingActor::Student,
        );

        $series->refresh();

        $this->assertSame(BookingSeriesStatus::Active, $series->status);
        $this->assertSame(BookingStatus::Cancelled, $second->refresh()->status);
        $this->assertSame(
            3,
            $series->bookings()->whereNot('status', BookingStatus::Cancelled)->count(),
        );
    }

    public function test_cancelling_this_and_following_ends_the_schedule_but_keeps_earlier_classes(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
        );

        $series = $result->series->refresh();
        $ordered = $series->bookings()->orderBy('starts_at')->get();
        $third = $ordered[2];

        app(BookingSeriesService::class)->cancelFrom(
            $series,
            $third,
            SeriesChangeScope::ThisAndFollowing,
            BookingActor::Student,
        );

        $series->refresh();

        $this->assertSame(BookingSeriesStatus::Cancelled, $series->status);
        $this->assertSame(BookingStatus::Pending, $ordered[0]->refresh()->status, 'earlier classes are untouched');
        $this->assertSame(BookingStatus::Pending, $ordered[1]->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $third->refresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $ordered[3]->refresh()->status);

        // Nothing further is ever generated for a cancelled schedule.
        $before = $series->bookings()->count();
        app(BookingSeriesService::class)->generate($series);
        $this->assertSame($before, $series->bookings()->count());
    }

    public function test_extending_a_schedule_adds_classes_without_recreating_the_existing_ones(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $series = $result->series->refresh();
        $originalIds = $series->bookings()->orderBy('starts_at')->pluck('id')->all();
        $originalReferences = $series->bookings()->orderBy('starts_at')->pluck('reference')->all();

        $seriesService = app(BookingSeriesService::class);
        $seriesService->extend($series, additionalClasses: 3);
        $seriesService->generate($series->refresh(), 50);

        $series->refresh();
        $after = $series->bookings()->orderBy('starts_at')->get();

        $this->assertSame(6, $after->count());
        $this->assertSame($originalIds, $after->take(3)->pluck('id')->all(), 'existing classes keep their identity');
        $this->assertSame($originalReferences, $after->take(3)->pluck('reference')->all());
        $this->assertSame(BookingSeriesStatus::Active, $series->status);
    }

    public function test_a_removed_date_is_never_booked_by_a_later_generation_pass(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 10,
            ),
        );

        $series = $result->series->refresh();
        $futureDate = $start->addWeeks(9)->toDateString();

        app(BookingSeriesService::class)->skipOccurrence(
            $series,
            $futureDate,
            BookingActor::Student,
        );

        // Force the whole remaining schedule to be laid down.
        $series->update(['generated_through_date' => null]);
        app(BookingSeriesService::class)->generate($series->refresh(), 200);

        $this->assertNotContains($futureDate, $this->bookedDates($series->refresh()));
    }

    // ── Compatibility ──────────────────────────────────────────────────────

    public function test_an_existing_custom_pattern_is_neither_rewritten_nor_misdescribed(): void
    {
        // The booking form no longer offers "every N weeks" or a
        // Daily/Weekly switch — days are picked directly. Series created
        // under the old form still hold a real interval, and they must
        // keep generating from it and keep being DESCRIBED by it. A
        // schedule quietly reshaped to fit a simpler form would move real
        // future classes.
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Tuesday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                interval: 3,
                weekdays: [Weekday::Tuesday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $series = $result->series->refresh();

        $this->assertSame(3, (int) $series->repeat_interval);
        $this->assertSame('Every 3 weeks on Tuesday', $series->describe());
        $this->assertSame([
            $start->toDateString(),
            $start->addWeeks(3)->toDateString(),
            $start->addWeeks(6)->toDateString(),
        ], $this->bookedDates($series));

        // Extending keeps the interval — it moves the END, nothing else.
        app(BookingSeriesService::class)->extend($series, additionalClasses: 1);
        app(BookingSeriesService::class)->generate($series->refresh(), 50);
        $series->refresh();

        // The interval survives, and the extra class lands where the
        // ORIGINAL rule says it should — three weeks after the last one,
        // not one week after it. (It sits beyond the confirmation
        // horizon, so it is scheduled rather than booked yet.)
        $this->assertSame(3, (int) $series->repeat_interval);
        $this->assertSame(4, (int) $series->occurrence_count);
        $this->assertSame($start->addWeeks(9)->toDateString(), app(RecurrenceScheduler::class)->lastLocalDate($series->rule()));
    }

    public function test_a_daily_series_describes_itself_as_daily(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Daily,
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 2,
            ),
        );

        $this->assertSame('Every day', $result->series->describe());
    }

    public function test_all_seven_weekdays_describes_itself_as_daily(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: Weekday::cases(),
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $series = $result->series->refresh();

        $this->assertSame('Every day', $series->describe());
        $this->assertSame([
            $start->toDateString(),
            $start->addDay()->toDateString(),
            $start->addDays(2)->toDateString(),
        ], $this->bookedDates($series));
    }

    public function test_every_class_still_carries_the_legacy_recurring_group_marker(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        $series = $result->series;

        foreach ($series->bookings as $booking) {
            $this->assertSame($series->id, $booking->meta['recurring_group'] ?? null);
            $this->assertSame('maths', $booking->meta['subject'] ?? null);
            $this->assertSame(5, $booking->meta['grade'] ?? null);
            $this->assertSame(RecurrenceFrequency::Weekly, $booking->recurrence_frequency);
        }

        $this->assertSame($series->id, $result->groupId);
    }

    public function test_a_bookings_series_link_is_null_for_a_one_time_class(): void
    {
        $this->payingStudent();

        $booking = Booking::factory()->create([
            'student_id' => auth()->id(),
            'instructor_id' => $this->teacher->id,
        ]);

        $this->assertNull($booking->booking_series_id);
        $this->assertNull($booking->series_occurrence_date);
        $this->assertNull($booking->series);
    }

    public function test_a_removed_date_stays_visible_so_it_can_be_put_back(): void
    {
        // A date that simply disappears is a removal the student cannot
        // undo. It is excluded from the schedule but still rendered.
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);
        $removed = $start->addWeek()->toDateString();

        $preview = $this->wizard()->previewSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 4,
            ),
            skippedDates: [$removed],
        );

        $dates = array_map(static fn ($o): string => $o->localDate, $preview->occurrences);
        $this->assertContains($removed, $dates);

        $row = collect($preview->occurrences)->firstWhere('localDate', $removed);
        $this->assertSame(SeriesOccurrenceStatus::Skipped, $row->status);
        // No place in the schedule, so no number — and it is not counted.
        $this->assertSame(0, $row->sequence);
        $this->assertSame(4, $preview->totalScheduled);
    }

    public function test_a_reserved_class_is_not_described_as_confirmed(): void
    {
        // A paid class awaiting payment is HELD, not confirmed. Saying
        // "confirmed" would tell the student their place is secure when
        // it is only being held until the payment window lapses.
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 2,
            ),
        );

        $series = $result->series->refresh();
        $schedule = app(BookingSeriesService::class)->scheduleFor($series);
        $first = $schedule->occurrences[0];

        $this->assertSame(BookingStatus::Pending, $series->bookings()->orderBy('starts_at')->first()->status);
        $this->assertSame(SeriesOccurrenceStatus::Reserved, $first->status);
        $this->assertStringContainsString('payment due', $first->status->label());
    }

    // ── Concurrency ────────────────────────────────────────────────────────

    public function test_the_database_itself_refuses_a_second_class_on_the_same_schedule_date(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 2,
            ),
        );

        $series = $result->series;
        $existing = $series->bookings()->orderBy('starts_at')->firstOrFail();

        // The last line of defence, tested directly: even a caller that
        // bypasses every service guard cannot create two classes for one
        // date of one schedule. This is what makes retries, duplicated
        // queue messages and racing workers safe rather than merely
        // unlikely.
        $this->expectException(QueryException::class);

        Booking::query()->create([
            'booking_type_id' => $existing->booking_type_id,
            'student_id' => $existing->student_id,
            'instructor_id' => $existing->instructor_id,
            'starts_at' => $existing->starts_at->addHours(2),
            'ends_at' => $existing->ends_at->addHours(2),
            'timezone' => 'UTC',
            'booking_series_id' => $series->id,
            'series_occurrence_date' => $existing->series_occurrence_date->toDateString(),
        ]);
    }

    // ── Payment semantics ──────────────────────────────────────────────────

    public function test_each_class_is_priced_and_reserved_separately(): void
    {
        $this->payingStudent();
        $start = $this->nextWeekdayAt(Weekday::Monday);

        $result = $this->wizard()->bookSeries(
            $this->wizardData($start),
            new RecurrencePatternData(
                frequency: RecurrenceFrequency::Weekly,
                weekdays: [Weekday::Monday],
                endCondition: RecurrenceEndCondition::AfterCount,
                occurrenceCount: 3,
            ),
        );

        foreach ($result->series->bookings as $booking) {
            // Unchanged per-class semantics: its own price, its own
            // pending payment, its own reservation hold.
            $this->assertSame(BookingStatus::Pending, $booking->status);
            $this->assertSame('499.00', $booking->price);
            $this->assertNotNull($booking->reserved_until);
            $this->assertNotNull($booking->reference);
        }

        $this->assertSame(
            3,
            $result->series->bookings()->distinct('reference')->count('reference'),
            'no two classes share a payment reference',
        );
    }
}
