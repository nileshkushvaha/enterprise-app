<?php

declare(strict_types=1);

namespace Tests\Feature\Lesson;

use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecordingStatus;
use App\Lessons\Contracts\LessonAttendanceServiceInterface;
use App\Lessons\Contracts\LessonFinalizationServiceInterface;
use App\Lessons\Contracts\LessonLifecycleServiceInterface;
use App\Lessons\DTOs\AttendanceEvidenceData;
use App\Lessons\Enums\AttendanceSource;
use App\Lessons\Enums\LessonOutcome;
use App\Lessons\Enums\LessonParticipant;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\Lesson;
use App\Models\Recording;
use App\Settings\LessonSettings;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The agreed lesson timing and completion policy: a 7–8 PM lesson opens
 * for joining at 6:50, closes at 8:05, and is completed 15–20 minutes
 * after 8 PM — by the time-based sweep today, by attendance evidence
 * once that is activated. Recording ingestion never completes a lesson,
 * silence never produces a no-show, and activation is gated and audited.
 */
final class LessonCompletionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private LessonLifecycleServiceInterface $lifecycle;

    private LessonFinalizationServiceInterface $finalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(LessonLifecycleServiceInterface::class);
        $this->finalizer = app(LessonFinalizationServiceInterface::class);
    }

    // ── Shipped values and cadence ───────────────────────────────────

    public function test_the_settings_migration_ships_the_agreed_timing_without_activating_evidence_finalization(): void
    {
        $meetings = app(MeetingSettings::class);
        $lessons = app(LessonSettings::class);

        $this->assertSame(10, $meetings->meeting_link_visible_before_minutes);
        $this->assertSame(5, $meetings->meeting_link_visible_after_minutes);
        $this->assertSame(5, $meetings->attendance_sync_delay_minutes);
        $this->assertSame(15, $lessons->auto_complete_grace_minutes);
        $this->assertSame(15, $lessons->attendance_finalize_delay_minutes);
        $this->assertFalse($lessons->automated_finalization_enabled, 'evidence-based finalization is activated separately, never by migration');
        $this->assertTrue($lessons->auto_complete_enabled);
    }

    public function test_completion_and_attendance_sync_run_every_five_minutes(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach (['lessons:auto-complete', 'lessons:finalize-due', 'meetings:sync-attendance'] as $command) {
            $event = $events->first(fn ($e) => str_contains((string) $e->command, $command));
            $this->assertNotNull($event, "{$command} is not scheduled");
            $this->assertSame('*/5 * * * *', $event->expression, "{$command} must run every five minutes");
            $this->assertTrue($event->withoutOverlapping);
        }
    }

    // ── 7–8 PM example ────────────────────────────────────────────────

    public function test_time_based_policy_completes_a_seven_to_eight_lesson_at_eight_fifteen_not_before(): void
    {
        $lesson = $this->lessonSevenToEight();

        $this->travelTo($this->at('20:14'));
        $this->assertSame(0, $this->lifecycle->autoCompleteDue());
        $this->assertTrue($lesson->refresh()->status->isOpen());
        $this->assertTrue($lesson->booking->isAwaitingCompletion());

        $this->travelTo($this->at('20:15'));
        $this->assertSame(1, $this->lifecycle->autoCompleteDue());

        $lesson->refresh();
        $this->assertSame(LessonOutcome::Completed, $lesson->outcome);
        $this->assertNotNull($lesson->auto_completed_at);
        $this->assertSame(BookingStatus::Completed, $lesson->booking->fresh()->status);
        $this->assertFalse($lesson->booking->fresh()->isAwaitingCompletion());
    }

    public function test_evidence_policy_completes_at_eight_fifteen_when_both_parties_have_qualifying_evidence(): void
    {
        $this->activateEvidencePolicy();
        $lesson = $this->lessonSevenToEight();
        $this->coverMeeting($lesson);
        $this->attend($lesson, LessonParticipant::Student, 'evt-s');
        $this->attend($lesson, LessonParticipant::Instructor, 'evt-i');

        $this->travelTo($this->at('20:14'));
        $this->assertSame(0, $this->finalizer->processDue(), 'the evidence window is still open at 8:14');
        $this->assertSame(0, $this->lifecycle->autoCompleteDue(), 'the time-based sweep defers while the evidence policy is active');

        $this->travelTo($this->at('20:15'));
        $this->assertSame(1, $this->finalizer->processDue());

        $lesson->refresh();
        $this->assertSame(LessonOutcome::Completed, $lesson->outcome);
        $this->assertNotNull($lesson->attendanceRecord->finalized_at, 'the record is sealed at the same 15-minute mark');
        $this->assertSame(BookingStatus::Completed, $lesson->booking->fresh()->status);
    }

    public function test_evidence_policy_never_completes_or_punishes_a_lesson_nobody_reported_on(): void
    {
        $this->activateEvidencePolicy();
        $lesson = $this->lessonSevenToEight();

        $this->travelTo($this->at('20:15')->addDays(3));
        $this->assertSame(0, $this->finalizer->processDue());
        $this->assertSame(0, $this->lifecycle->autoCompleteDue());

        $lesson->refresh();
        $this->assertSame(LessonOutcome::Pending, $lesson->outcome);
        $this->assertTrue($lesson->status->isOpen());
        $this->assertSame(BookingStatus::Confirmed, $lesson->booking->fresh()->status);
    }

    public function test_a_recording_becoming_available_does_not_complete_the_lesson(): void
    {
        $lesson = $this->lessonSevenToEight();
        $meeting = $this->coverMeeting($lesson);
        Recording::factory()->create([
            'booking_id' => $lesson->booking_id,
            'booking_meeting_id' => $meeting->id,
            'student_id' => $lesson->student_id,
            'teacher_id' => $lesson->instructor_id,
            'status' => RecordingStatus::Available,
        ]);

        $this->travelTo($this->at('20:05'));
        $this->assertSame(0, $this->lifecycle->autoCompleteDue());
        $this->assertSame(0, $this->finalizer->processDue());
        $this->assertTrue($lesson->refresh()->status->isOpen());
        $this->assertSame(LessonOutcome::Pending, $lesson->outcome);
    }

    // ── Preflight and activation ─────────────────────────────────────

    public function test_preflight_is_read_only_and_blocks_activation_while_no_enabled_provider_reports_attendance(): void
    {
        $before = app(LessonSettings::class)->toArray();

        $this->artisan('lessons:completion-preflight')
            ->expectsOutputToContain('time-based')
            ->expectsOutputToContain('No enabled meeting provider can report attendance')
            ->expectsOutputToContain('Nothing was changed')
            ->assertExitCode(1);

        $this->assertSame($before, app(LessonSettings::class)->refresh()->toArray());
    }

    public function test_preflight_json_lists_open_ended_lessons_without_changing_them(): void
    {
        $lesson = $this->lessonSevenToEight();
        $this->travelTo($this->at('20:30'));

        $this->artisan('lessons:completion-preflight --json')->assertExitCode(1);

        $this->assertTrue($lesson->refresh()->status->isOpen(), 'the report finalizes nothing');
        $this->assertSame(LessonOutcome::Pending, $lesson->outcome);
    }

    public function test_activation_refuses_while_blockers_exist_and_writes_nothing(): void
    {
        $this->artisan('lessons:evidence-finalization on --confirm')
            ->expectsOutputToContain('Refusing to activate')
            ->assertExitCode(1);

        $this->assertFalse(app(LessonSettings::class)->refresh()->automated_finalization_enabled);
        $this->assertDatabaseMissing('activity_log', ['event' => 'lesson_evidence_finalization_activated']);
    }

    public function test_activation_is_a_dry_run_without_confirm_and_audited_with_it(): void
    {
        $this->makeAttendanceReady();

        $this->artisan('lessons:completion-preflight')->assertExitCode(0);

        $this->artisan('lessons:evidence-finalization on')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);
        $this->assertFalse(app(LessonSettings::class)->refresh()->automated_finalization_enabled);

        $this->artisan('lessons:evidence-finalization on --confirm')
            ->expectsOutputToContain('now on')
            ->assertExitCode(0);
        $this->assertTrue(app(LessonSettings::class)->refresh()->automated_finalization_enabled);

        $activity = Activity::query()->where('event', 'lesson_evidence_finalization_activated')->first();
        $this->assertNotNull($activity);
        $this->assertSame('lessons.automated_finalization_enabled', $activity->properties['setting']);
        $this->assertFalse($activity->properties['previous']);
        $this->assertTrue($activity->properties['new']);

        // Rollback is always allowed and audited too.
        $this->artisan('lessons:evidence-finalization off --confirm')->assertExitCode(0);
        $this->assertFalse(app(LessonSettings::class)->refresh()->automated_finalization_enabled);
        $this->assertDatabaseHas('activity_log', ['event' => 'lesson_evidence_finalization_deactivated']);
    }

    public function test_activation_rejects_an_unknown_state(): void
    {
        $this->artisan('lessons:evidence-finalization maybe --confirm')->assertExitCode(2);
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-09-13 {$time}:00", 'UTC');
    }

    /** A confirmed 19:00–20:00 UTC lesson, created before it starts. */
    private function lessonSevenToEight(): Lesson
    {
        $this->travelTo($this->at('18:00'));

        $booking = Booking::factory()->confirmed()->create([
            'starts_at' => $this->at('19:00'),
            'ends_at' => $this->at('20:00'),
            'timezone' => 'UTC',
        ]);

        return $this->lifecycle->createFromBooking($booking);
    }

    /** A created meeting whose provider attendance pull settled: evidence coverage. */
    private function coverMeeting(Lesson $lesson): BookingMeeting
    {
        return BookingMeeting::factory()->created()->create([
            'booking_id' => $lesson->booking_id,
            'starts_at' => $lesson->starts_at,
            'ends_at' => $lesson->ends_at,
            'attendance_sync_status' => 'synced',
            'attendance_synced_at' => $lesson->ends_at->addMinutes(6),
        ]);
    }

    private function attend(Lesson $lesson, LessonParticipant $participant, string $eventId): void
    {
        app(LessonAttendanceServiceInterface::class)->record($lesson, new AttendanceEvidenceData(
            participant: $participant,
            source: AttendanceSource::ProviderSync,
            joinedAt: CarbonImmutable::parse($lesson->starts_at),
            leftAt: CarbonImmutable::parse($lesson->ends_at),
            providerReference: 'meeting-1',
            providerEventId: $eventId,
        ));
    }

    private function activateEvidencePolicy(): void
    {
        $settings = app(LessonSettings::class);
        $settings->automated_finalization_enabled = true;
        $settings->save();
    }

    /** The testing-only fake provider is the one attendance-capable adapter. */
    private function makeAttendanceReady(): void
    {
        $settings = app(MeetingSettings::class);
        $settings->meetings_enabled = true;
        $settings->default_provider = 'fake';
        $settings->attendance_sync_enabled = true;
        $settings->save();
    }
}
