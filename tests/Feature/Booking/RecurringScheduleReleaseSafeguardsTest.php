<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\WizardBookingServiceInterface;
use App\Booking\DTOs\RecurrencePatternData;
use App\Booking\DTOs\WizardBookingData;
use App\Booking\Enums\RecurrenceEndCondition;
use App\Booking\Enums\RecurrenceFrequency;
use App\Booking\Enums\Weekday;
use App\Booking\Events\BookingSeriesOccurrenceUnavailable;
use App\Booking\Exceptions\BookingException;
use App\Booking\Services\BookingSeriesService;
use App\Listeners\Booking\SendBookingNotifications;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\BookingSeriesException;
use App\Models\BookingType;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubject;
use App\Models\TeacherUnavailability;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\Booking\BookingSeriesOccurrenceUnavailableNotification;
use App\Settings\BookingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesAcademicBookingContext;
use Tests\Support\CreatesStudentLessonPrices;
use Tests\TestCase;

/**
 * The safeguards that stand between "the code works" and "this is safe
 * to release".
 *
 * Two independent things are covered. First, the release gate: a
 * schedule that can only be kept if a background job actually runs on
 * this deployment must be refused — with an explanation — until someone
 * has confirmed that it does. Second, the thing that gate is waiting
 * for: a student who loses a future class must be TOLD, exactly once,
 * and must be able to get the class back once the obstacle is gone.
 */
class RecurringScheduleReleaseSafeguardsTest extends TestCase
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

        $settings = app(BookingSettings::class);
        $settings->maximum_advance_booking_days = 3650;
        $settings->max_daily_bookings_per_teacher = null;
        // The flag's default. Individual tests turn it on deliberately.
        $settings->recurring_future_generation_enabled = false;
        $settings->save();
    }

    private function allowFutureGeneration(bool $enabled = true): void
    {
        $settings = app(BookingSettings::class);
        $settings->recurring_future_generation_enabled = $enabled;
        $settings->save();
    }

    private function payingStudent(): User
    {
        $priced = $this->createPaidBookingTypeWithPrice('paid_one_to_one', 499.00, 'INR', durationMinutes: 60);
        $student = User::factory()->activeStudent()->create(['status' => User::STATUS_ACTIVE]);
        $this->assignBillingCountry($student, $priced['country']);
        $this->actingAs($student);

        return $student;
    }

    private function nextMondayAt(int $hour = 10): CarbonImmutable
    {
        $date = CarbonImmutable::now('UTC')->addDays(3)->setTime($hour, 0)->startOfHour();

        while ((int) $date->dayOfWeek !== Weekday::Monday->value) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function wizardData(CarbonImmutable $startsAt): WizardBookingData
    {
        return new WizardBookingData(
            typeKey: 'paid_one_to_one',
            subject: 'maths',
            grade: 5,
            startsAt: $startsAt,
            timezone: 'UTC',
            teacherId: $this->teacher->id,
        );
    }

    private function weeklyPattern(int $classes): RecurrencePatternData
    {
        return new RecurrencePatternData(
            frequency: RecurrenceFrequency::Weekly,
            weekdays: [Weekday::Monday],
            endCondition: RecurrenceEndCondition::AfterCount,
            occurrenceCount: $classes,
        );
    }

    private function wizard(): WizardBookingServiceInterface
    {
        return app(WizardBookingServiceInterface::class);
    }

    // ── 1. The release gate ────────────────────────────────────────────────

    public function test_the_flag_is_off_by_default(): void
    {
        // The whole point: a fresh deployment must not accept schedules
        // it cannot keep until someone has checked that it can.
        $this->assertFalse(app(BookingSeriesService::class)->futureGenerationEnabled());
    }

    public function test_an_ongoing_schedule_is_refused_while_the_flag_is_off(): void
    {
        $this->payingStudent();

        try {
            $this->wizard()->bookSeries(
                $this->wizardData($this->nextMondayAt()),
                new RecurrencePatternData(
                    frequency: RecurrenceFrequency::Weekly,
                    weekdays: [Weekday::Monday],
                    endCondition: RecurrenceEndCondition::Never,
                ),
            );

            $this->fail('an ongoing schedule must be refused while future generation is off');
        } catch (BookingException $exception) {
            $this->assertStringContainsString('not available just yet', $exception->getMessage());
        }

        // Refused outright — no half-created schedule left behind.
        $this->assertSame(0, BookingSeries::query()->count());
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_a_long_finite_schedule_is_refused_rather_than_truncated(): void
    {
        // The case that is easy to get wrong: a FINITE schedule that
        // simply reaches too far. Booking the eight classes that fit and
        // dropping the other thirty-two would be silent truncation.
        $this->payingStudent();

        try {
            $this->wizard()->bookSeries($this->wizardData($this->nextMondayAt()), $this->weeklyPattern(40));

            $this->fail('a schedule reaching past the horizon must be refused while the flag is off');
        } catch (BookingException $exception) {
            $this->assertStringContainsString('further ahead than we can confirm', $exception->getMessage());
            $this->assertStringContainsString('extend the schedule later', $exception->getMessage());
        }

        $this->assertSame(0, BookingSeries::query()->count());
        $this->assertSame(0, Booking::query()->count(), 'nothing may be booked from a refused schedule');
    }

    public function test_a_schedule_inside_the_horizon_is_unaffected_by_the_flag(): void
    {
        // The flag gates future generation, not repeating classes. A
        // schedule that can be reserved in full stays bookable.
        $this->payingStudent();

        $result = $this->wizard()->bookSeries($this->wizardData($this->nextMondayAt()), $this->weeklyPattern(4));

        $this->assertNotNull($result->series);
        $this->assertSame(4, $result->booked->count());
        $this->assertSame(0, (int) $result->plannedCount, 'every class is reserved, so nothing is owed');
    }

    public function test_the_same_long_schedule_is_accepted_once_the_flag_is_on(): void
    {
        // Not a cap: with the gate open there is no limit.
        $this->allowFutureGeneration();
        $this->payingStudent();

        $result = $this->wizard()->bookSeries($this->wizardData($this->nextMondayAt()), $this->weeklyPattern(40));

        $this->assertSame(40, (int) $result->series->occurrence_count);
        $this->assertGreaterThan(0, $result->booked->count());
        $this->assertGreaterThan(0, (int) $result->plannedCount);
    }

    public function test_the_json_api_is_gated_by_the_same_flag(): void
    {
        // The gate lives in the service every path funnels through, so
        // it cannot be walked around by using a different entry point.
        $this->payingStudent();

        $this->postJson('/dashboard/bookings', [
            'type' => 'paid_one_to_one',
            'teacher_id' => $this->teacher->id,
            'starts_at' => $this->nextMondayAt()->toIso8601String(),
            'subject' => 'maths',
            'grade' => 5,
            'recurring' => true,
            'occurrences' => 40,
            'frequency' => 'weekly',
        ])->assertStatus(422);

        $this->assertSame(0, BookingSeries::query()->count());
    }

    public function test_the_preview_reports_the_gate_before_anything_is_confirmed(): void
    {
        $this->payingStudent();

        $preview = $this->wizard()->previewSeries($this->wizardData($this->nextMondayAt()), $this->weeklyPattern(40));

        $this->assertTrue($preview->requiresFutureGeneration);
        $this->assertFalse($preview->futureGenerationAvailable);
        $this->assertTrue($preview->isBlockedByFutureGeneration());

        $this->allowFutureGeneration();

        $open = $this->wizard()->previewSeries($this->wizardData($this->nextMondayAt()), $this->weeklyPattern(40));

        $this->assertTrue($open->requiresFutureGeneration);
        $this->assertFalse($open->isBlockedByFutureGeneration());
    }

    // ── 2. Telling the student ─────────────────────────────────────────────

    /**
     * A schedule that has already been booked, and a date far enough out
     * that it was never checked — which then stops being available
     * before its turn comes.
     *
     * Time is moved forward deliberately rather than the horizon being
     * faked: the whole scenario only exists BECAUSE the horizon advances
     * with the calendar, and a fixture that skipped that would be
     * testing something that cannot happen.
     *
     * @return array{BookingSeries, CarbonImmutable} the series, and the date that will fail
     */
    private function seriesWithAFutureBlocker(): array
    {
        $this->allowFutureGeneration();
        $this->payingStudent();

        $start = $this->nextMondayAt();

        $result = $this->wizard()->bookSeries($this->wizardData($start), $this->weeklyPattern(40));
        $series = $result->series->refresh();

        // The very next class after the ones already reserved: beyond the
        // horizon, so it was never checked at booking time.
        $lastBooked = CarbonImmutable::parse(
            $series->bookings()->orderByDesc('starts_at')->first()->series_occurrence_date->toDateString().' '.$start->format('H:i:s'),
            'UTC',
        );
        $blocked = $lastBooked->addWeek();

        TeacherUnavailability::query()->create([
            'teacher_id' => $this->teacher->id,
            'starts_at' => $blocked->startOfDay(),
            'ends_at' => $blocked->endOfDay(),
            'timezone' => 'UTC',
            'reason' => 'Leave',
        ]);

        // A week passes. The horizon rolls forward and that class is now
        // due — precisely the state the hourly sweep runs in.
        $this->travelTo($lastBooked->addDay());

        return [$series, $blocked];
    }

    public function test_a_lost_future_class_dispatches_the_domain_event(): void
    {
        Event::fake([BookingSeriesOccurrenceUnavailable::class]);

        [$series, $blocked] = $this->seriesWithAFutureBlocker();

        app(BookingSeriesService::class)->generate($series->refresh(), 50);

        Event::assertDispatched(
            BookingSeriesOccurrenceUnavailable::class,
            fn (BookingSeriesOccurrenceUnavailable $event): bool => $event->localDate === $blocked->toDateString()
                && $event->series->id === $series->id
                && $event->reason !== null,
        );
    }

    public function test_the_student_is_notified_with_the_date_time_and_timezone(): void
    {
        Notification::fake();

        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $student = $series->student;

        app(BookingSeriesService::class)->generate($series->refresh(), 50);

        // Dispatched through the ordinary participant pipeline, not from
        // the service, so it inherits the same channels and guards as
        // every other booking message.
        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable(
            new BookingSeriesOccurrenceUnavailable(
                $series,
                $blocked->toDateString(),
                $blocked,
                'Your instructor is not available at this time on this date.',
            ),
        );

        Notification::assertSentTo(
            $student,
            BookingSeriesOccurrenceUnavailableNotification::class,
            function (BookingSeriesOccurrenceUnavailableNotification $notification) use ($blocked, $student): bool {
                $mail = $notification->toMail($student);
                $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

                return str_contains($body, $blocked->format('D, M j Y'))
                    && str_contains($body, 'UTC')
                    && $mail->actionUrl !== null;
            },
        );
    }

    public function test_the_instructor_is_not_notified(): void
    {
        Notification::fake();

        [$series, $blocked] = $this->seriesWithAFutureBlocker();

        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable(
            new BookingSeriesOccurrenceUnavailable($series, $blocked->toDateString(), $blocked, 'Unavailable.'),
        );

        // Nothing for them to act on — the slot was unavailable in their
        // own calendar, which is why it failed.
        Notification::assertNotSentTo($this->teacher, BookingSeriesOccurrenceUnavailableNotification::class);
    }

    public function test_a_retried_generation_pass_does_not_notify_twice(): void
    {
        Event::fake([BookingSeriesOccurrenceUnavailable::class]);

        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $service = app(BookingSeriesService::class);

        // Three passes over the same unbookable date — a retried job, a
        // duplicated queue message, the next hour's sweep.
        $service->generate($series->refresh(), 50);

        // Two further passes forced back over the same date — a retried
        // job, a duplicated queue message, the next hour's sweep.
        $series->refresh()->update(['generated_through_date' => $blocked->subDay()->toDateString()]);
        $service->generate($series->refresh(), 50);
        $series->refresh()->update(['generated_through_date' => $blocked->subDay()->toDateString()]);
        $service->generate($series->refresh(), 50);

        Event::assertDispatchedTimes(BookingSeriesOccurrenceUnavailable::class, 1);

        $exception = BookingSeriesException::query()
            ->where('booking_series_id', $series->id)
            ->where('local_date', $blocked->toDateString())
            ->get();

        $this->assertCount(1, $exception, 'one row per lost date, however many passes touch it');
        // The durable stamp is what survives a worker restart, not an
        // in-memory guard.
        $this->assertNotNull($exception->first()->notified_at);
    }

    public function test_a_redelivered_event_does_not_notify_twice(): void
    {
        Notification::fake();

        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $event = new BookingSeriesOccurrenceUnavailable($series, $blocked->toDateString(), $blocked, 'Unavailable.');

        // The queue is at-least-once: the same event can arrive twice.
        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable($event);
        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable($event);

        Notification::assertSentToTimes($series->student, BookingSeriesOccurrenceUnavailableNotification::class, 1);
    }

    public function test_two_different_lost_dates_each_notify(): void
    {
        Notification::fake();

        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $other = $blocked->addWeek();

        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable(
            new BookingSeriesOccurrenceUnavailable($series, $blocked->toDateString(), $blocked, 'Unavailable.'),
        );
        app(SendBookingNotifications::class)->handleSeriesOccurrenceUnavailable(
            new BookingSeriesOccurrenceUnavailable($series, $other->toDateString(), $other, 'Unavailable.'),
        );

        Notification::assertSentToTimes($series->student, BookingSeriesOccurrenceUnavailableNotification::class, 2);
    }

    // ── 3. Recovery ────────────────────────────────────────────────────────

    public function test_a_lost_class_can_be_recovered_once_the_obstacle_is_gone(): void
    {
        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $service = app(BookingSeriesService::class);

        $service->generate($series->refresh(), 50);

        $this->assertDatabaseHas('booking_series_exceptions', [
            'booking_series_id' => $series->id,
            'local_date' => $blocked->toDateString(),
            'action' => BookingSeriesException::ACTION_CONFLICT,
        ]);

        // The instructor's leave is cancelled. The ordinary sweep will
        // never revisit this date — it is behind the watermark — so the
        // student asks for it directly.
        TeacherUnavailability::query()->delete();

        $booking = $service->retryOccurrence($series->refresh(), $blocked->toDateString());

        $this->assertNotNull($booking, 'the class should have been booked on retry');
        $this->assertSame($blocked->toDateString(), $booking->series_occurrence_date->toDateString());
        $this->assertDatabaseMissing('booking_series_exceptions', [
            'booking_series_id' => $series->id,
            'local_date' => $blocked->toDateString(),
        ]);
    }

    public function test_a_retry_that_fails_keeps_the_date_and_does_not_notify_again(): void
    {
        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $service = app(BookingSeriesService::class);

        $service->generate($series->refresh(), 50);

        // Faked only now, so the one legitimate message the sweep already
        // sent is not what this counts.
        Notification::fake();

        // Still unavailable — the leave has not been lifted.
        $booking = $service->retryOccurrence($series->refresh(), $blocked->toDateString());

        $this->assertNull($booking);
        // The date is not lost: it is still recorded, so it stays visible
        // and can be tried again.
        $this->assertDatabaseHas('booking_series_exceptions', [
            'booking_series_id' => $series->id,
            'local_date' => $blocked->toDateString(),
            'action' => BookingSeriesException::ACTION_CONFLICT,
        ]);

        // The student triggered this and is reading the answer on screen;
        // emailing them about it as well would be noise.
        Notification::assertNotSentTo($series->student, BookingSeriesOccurrenceUnavailableNotification::class);
    }

    public function test_a_recovered_class_is_visible_in_the_schedule_before_and_after(): void
    {
        [$series, $blocked] = $this->seriesWithAFutureBlocker();
        $service = app(BookingSeriesService::class);

        $service->generate($series->refresh(), 50);

        $page = 1;
        $found = null;

        // A lost date must remain VISIBLE in the schedule, or the student
        // has no way to ask for it back.
        while ($page <= 10 && $found === null) {
            $schedule = $service->scheduleFor($series->refresh(), $page);

            foreach ($schedule->occurrences as $occurrence) {
                if ($occurrence->localDate === $blocked->toDateString()) {
                    $found = $occurrence;
                    break;
                }
            }

            if (! $schedule->hasMore) {
                break;
            }

            $page++;
        }

        $this->assertNotNull($found, 'the lost date must still appear in the schedule');
        $this->assertTrue($found->isConflict());
        $this->assertNotNull($found->reason);
    }
}
