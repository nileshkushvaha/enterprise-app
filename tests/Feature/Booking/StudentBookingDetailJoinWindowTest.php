<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\RecordingStatus;
use App\Enums\InstructorStatus;
use App\Enums\StudentStatus;
use App\Lessons\Enums\LessonOutcome;
use App\Livewire\Frontend\Student\BookingDetail;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\BookingType;
use App\Models\Lesson;
use App\Models\Recording;
use App\Models\User;
use App\Settings\FeatureSettings;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The student's booking page around the join window (15 min before, 15
 * min after by default): one join action, driven by the server-side
 * decision, with the window edges spelled out — and never a join link
 * or passcode once the window has closed.
 */
final class StudentBookingDetailJoinWindowTest extends TestCase
{
    use RefreshDatabase;

    private const string JOIN_URL = 'https://us05web.zoom.us/j/82122025909?pwd=participant';

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);

        $settings = app(MeetingSettings::class);
        $settings->student_join_url_visible = true;
        $settings->meeting_link_visible_before_minutes = 15;
        $settings->meeting_link_visible_after_minutes = 15;
        $settings->save();

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
        $this->student->profile()->update(['student_status' => StudentStatus::Active, 'timezone' => 'UTC']);
    }

    /** A confirmed lesson scheduled 19:00–20:00 UTC on the given day, with a created Zoom meeting. */
    private function lessonAt(CarbonImmutable $startsAt): Booking
    {
        $instructor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $instructor->assignRole('instructor');
        $instructor->profile()->update(['instructor_status' => InstructorStatus::Active]);

        $type = BookingType::query()->where('key', 'free_demo')->first()
            ?? BookingType::factory()->create(['key' => 'free_demo', 'duration_minutes' => 60]);

        $booking = Booking::factory()->for($type, 'type')->create([
            'student_id' => $this->student->id,
            'instructor_id' => $instructor->id,
            'status' => BookingStatus::Confirmed,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'timezone' => 'UTC',
        ]);

        BookingMeeting::factory()->zoom()->created(self::JOIN_URL)->create([
            'booking_id' => $booking->id,
            'password' => 'pass1234',
            'provider_meeting_id' => '82122025909',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);

        return $booking->fresh();
    }

    private function page(Booking $booking): Testable
    {
        return Livewire::actingAs($this->student)->test(BookingDetail::class, ['bookingId' => $booking->id]);
    }

    public function test_the_schedule_is_shown_once_as_a_range_with_the_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 10:00:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $html = $this->page($booking)->html();

        $this->assertSame(1, substr_count($html, '7:00 PM–8:00 PM'), 'the time range appears exactly once');
        $this->assertSame(1, substr_count($html, 'Reference '.$booking->reference));
        $this->assertStringContainsString('(UTC)', $html);
        $this->assertStringNotContainsString('Booked in', $html, 'the booking timezone equals the viewer\'s, so provenance is not repeated');
    }

    public function test_before_the_window_the_page_says_when_joining_opens_and_offers_no_link_or_passcode(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 18:30:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $this->page($booking)
            ->assertSee('Joining opens at')
            ->assertSee('6:45 PM')
            ->assertDontSee('Join the lesson')
            ->assertDontSee('pass1234')
            ->assertDontSee(self::JOIN_URL);
    }

    public function test_at_the_opening_boundary_the_single_join_action_and_passcode_appear(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 18:45:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $html = $this->page($booking)->html();

        $this->assertSame(1, substr_count($html, 'Join the lesson'), 'exactly one join action');
        $this->assertSame(1, substr_count($html, 'pass1234'), 'the passcode is shown once');
        $this->assertStringContainsString(route('dashboard.meetings.join', $booking), $html);
        $this->assertStringNotContainsString(self::JOIN_URL, $html, 'the provider URL is only ever the gateway\'s redirect');
        $this->assertStringNotContainsString('Joining opens at', $html);
        $this->assertStringContainsString('wire:poll.60s', $html, 'time-sensitive state refreshes on an open page');
    }

    public function test_during_the_grace_period_the_page_explains_the_end_and_the_closing_time_without_contradiction(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 20:05:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $this->page($booking)
            ->assertSee('Join the lesson')
            ->assertSee('Scheduled time ended. Joining closes at 8:15 PM.')
            ->assertDontSee('This lesson is in progress')
            ->assertDontSee('Joining closed at');
    }

    public function test_after_the_window_closes_the_join_action_and_passcode_are_gone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 20:16:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $html = $this->page($booking)->html();

        $this->assertStringNotContainsString('Join the lesson', $html);
        $this->assertStringNotContainsString('pass1234', $html);
        $this->assertStringNotContainsString(self::JOIN_URL, $html);
        $this->assertStringContainsString('Joining closed at 8:15 PM', $html);
        $this->assertStringNotContainsString('wire:poll.60s', $html, 'nothing left to refresh');
        $this->assertSame(1, substr_count($html, 'This lesson has ended'), 'the ended state is said once');
    }

    public function test_an_ended_lesson_awaiting_completion_says_so_instead_of_promising_a_recording(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 20:30:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $this->page($booking)
            ->assertSee('Awaiting completion')
            ->assertSee('If this lesson was recorded')
            ->assertDontSee('Recording processing')
            ->assertDontSee('within an hour');
    }

    public function test_a_delivered_lesson_with_a_pending_recording_shows_processing_without_a_fixed_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 21:00:00', 'UTC'));
        $booking = $this->lessonAt(CarbonImmutable::parse('2026-09-13 19:00:00', 'UTC'));

        $features = app(FeatureSettings::class);
        $features->recording_enabled = true;
        $features->save();
        $meetings = app(MeetingSettings::class);
        $meetings->recording_enabled = true;
        $meetings->recording_student_playback_enabled = true;
        $meetings->save();

        Lesson::factory()->create([
            'booking_id' => $booking->id,
            'student_id' => $this->student->id,
            'instructor_id' => $booking->instructor_id,
            'outcome' => LessonOutcome::Completed,
            'outcome_finalized_at' => now(),
            'outcome_version' => 1,
        ]);
        Recording::factory()->create([
            'booking_id' => $booking->id,
            'booking_meeting_id' => $booking->meeting->id,
            'student_id' => $this->student->id,
            'teacher_id' => $booking->instructor_id,
            'provider' => 'zoom',
            'status' => RecordingStatus::Pending,
        ]);

        $this->page($booking)
            ->assertSee('Recording processing')
            ->assertSee('will appear here when it is ready')
            ->assertDontSee('Awaiting completion')
            ->assertDontSee('within an hour');
    }
}
