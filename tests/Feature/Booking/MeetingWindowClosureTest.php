<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Contracts\GoogleMeetClient;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingJoinAvailability;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\BookingException;
use App\Booking\Exceptions\GatewayRequestException;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FakeGoogleMeetClient;
use Tests\TestCase;

/**
 * A lesson's meeting is bounded by its own timeslot.
 *
 * Participants may join from 15 minutes before the start until 15
 * minutes after the end, and at that same boundary the meeting is
 * closed at the provider — otherwise a Google Meet space keeps running
 * after class, and on a recording-eligible lesson keeps RECORDING, so
 * the platform stores footage of a room nobody is teaching in.
 *
 * The two halves share one window calculation on purpose: SIRI must
 * never advertise a link to a meeting it has closed, nor close a
 * meeting while it is still handing the link out.
 */
final class MeetingWindowClosureTest extends TestCase
{
    use RefreshDatabase;

    private const DELEGATED_ACCOUNT = 'meetings@example.test';

    private const SPACE = 'spaces/lesson-space-1';

    private FakeGoogleMeetClient $meet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meet = new FakeGoogleMeetClient;
        $this->app->instance(GoogleMeetClient::class, $this->meet);

        $settings = app(MeetingSettings::class);
        $settings->meetings_enabled = true;
        $settings->meeting_auto_close_enabled = true;
        $settings->meeting_link_visible_before_minutes = 15;
        $settings->meeting_link_visible_after_minutes = 15;
        $settings->google_meet_enabled = true;
        $settings->google_auth_type = 'service_account';
        $settings->google_calendar_id = 'calendar-123@group.calendar.google.com';
        $settings->platform_meeting_account = self::DELEGATED_ACCOUNT;
        $settings->google_credentials_json = Crypt::encryptString((string) json_encode([
            'type' => 'service_account',
            'client_email' => 'svc@project.iam.gserviceaccount.com',
            'private_key' => 'FAKE_PRIVATE_KEY_TOKEN',
        ]));
        $settings->save();
    }

    /** A lesson from 10:00 to 11:00, with the meeting SIRI created the Meet space for. */
    private function lesson(CarbonImmutable $startsAt, ?array $metadata = ['space' => self::SPACE]): BookingMeeting
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);

        return BookingMeeting::factory()->google()->created('https://meet.google.com/abc-defg-hjk')->create([
            'booking_id' => $booking->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'provider_meeting_id' => 'abc-defg-hjk',
            'metadata' => $metadata,
        ]);
    }

    private function service(): BookingMeetingServiceInterface
    {
        return app(BookingMeetingServiceInterface::class);
    }

    // ── The join window itself ───────────────────────────────────────────

    public function test_the_link_opens_fifteen_minutes_before_the_lesson_and_not_before(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);
        $booking = $meeting->booking->load('meeting');

        $this->travelTo($start->subMinutes(16));
        $this->assertSame(MeetingJoinAvailability::TooEarly, $this->service()->joinAvailabilityFor($booking, true));

        $this->travelTo($start->subMinutes(15));
        $this->assertSame(MeetingJoinAvailability::Available, $this->service()->joinAvailabilityFor($booking, true));
    }

    public function test_the_link_closes_fifteen_minutes_after_the_lesson_ends(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);
        $booking = $meeting->booking->load('meeting');

        // 11:15 — the last moment of the window.
        $this->travelTo($start->addHour()->addMinutes(15));
        $this->assertSame(MeetingJoinAvailability::Available, $this->service()->joinAvailabilityFor($booking, true));

        $this->travelTo($start->addHour()->addMinutes(16));
        $this->assertSame(MeetingJoinAvailability::Unavailable, $this->service()->joinAvailabilityFor($booking, true));
    }

    public function test_the_close_boundary_is_the_same_instant_the_link_window_ends(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);

        $this->assertTrue(
            $this->service()->joinWindowEndsAt($meeting)->equalTo($start->addHour()->addMinutes(15)),
        );
    }

    // ── Closing at the provider ──────────────────────────────────────────

    public function test_a_finished_lesson_is_restricted_and_its_conference_ended(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->assertTrue($this->service()->closeExpiredMeeting($meeting));

        $this->assertSame([self::SPACE], $this->meet->conferencesEnded);
        $this->assertSame('RESTRICTED', $this->meet->spacesRestricted[self::SPACE]);
        $this->assertNotNull($meeting->refresh()->metadata['closed_at']);
        $this->assertTrue($meeting->metadata['closed_at_provider']);
        // Closing ends what is running; it never rewrites how the
        // meeting was created, nor the booking.
        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertSame(BookingStatus::Confirmed, $meeting->booking->refresh()->status);
    }

    public function test_a_lesson_still_inside_its_window_is_never_touched(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);

        // Mid-lesson, and again at the very last joinable minute.
        $this->travelTo($start->addMinutes(30));
        $this->assertFalse($this->service()->closeExpiredMeeting($meeting));

        $this->travelTo($start->addHour()->addMinutes(15));
        $this->assertFalse($this->service()->closeExpiredMeeting($meeting));

        $this->assertSame([], $this->meet->conferencesEnded);
        $this->assertNull($meeting->refresh()->metadata['closed_at'] ?? null);
    }

    public function test_closing_is_idempotent(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->assertTrue($this->service()->closeExpiredMeeting($meeting));
        $this->assertFalse($this->service()->closeExpiredMeeting($meeting->refresh()));

        $this->assertCount(1, $this->meet->conferencesEnded);
    }

    /** A Calendar-created conference carries no space SIRI may act on — a boundary, not a failure. */
    public function test_a_meeting_without_a_meet_space_is_left_to_link_withholding(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start, metadata: ['conference_status' => 'success']);

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->assertFalse($this->service()->closeExpiredMeeting($meeting));
        $this->assertSame([], $this->meet->conferencesEnded);
        $this->assertFalse($meeting->refresh()->metadata['closed_at_provider']);
    }

    public function test_a_provider_failure_leaves_the_meeting_for_the_next_sweep(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);
        $this->meet->throwOnEndActiveConference = new GatewayRequestException('Meet API unavailable');

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->expectException(BookingException::class);

        try {
            $this->service()->closeExpiredMeeting($meeting);
        } finally {
            $this->assertNull($meeting->refresh()->metadata['closed_at'] ?? null);
        }
    }

    /** Restriction is best-effort: stopping what is running matters more than preventing a rejoin. */
    public function test_a_failed_restriction_still_ends_the_conference(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);
        $this->meet->throwOnRestrictSpaceAccess = new GatewayRequestException('patch refused');

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->assertTrue($this->service()->closeExpiredMeeting($meeting));
        $this->assertSame([self::SPACE], $this->meet->conferencesEnded);
    }

    public function test_auto_close_can_be_switched_off(): void
    {
        $settings = app(MeetingSettings::class);
        $settings->meeting_auto_close_enabled = false;
        $settings->save();

        $start = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $meeting = $this->lesson($start);

        $this->travelTo($start->addHour()->addMinutes(16));

        $this->assertFalse($this->service()->closeExpiredMeeting($meeting));
        $this->assertSame([], $this->meet->conferencesEnded);
    }

    // ── The sweep ────────────────────────────────────────────────────────

    public function test_the_sweep_closes_finished_lessons_and_leaves_live_ones_alone(): void
    {
        $now = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');
        $this->travelTo($now);

        $finished = $this->lesson($now->subHours(2));   // ended 11:00, window closed 11:15
        $live = $this->lesson($now->subMinutes(10));    // still running

        $this->artisan('meetings:close-expired')->assertExitCode(0);

        $this->assertSame([self::SPACE], $this->meet->conferencesEnded);
        $this->assertNotNull($finished->refresh()->metadata['closed_at']);
        $this->assertNull($live->refresh()->metadata['closed_at'] ?? null);
    }

    public function test_the_sweep_ignores_meetings_that_are_not_created(): void
    {
        $now = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');
        $this->travelTo($now);

        $meeting = $this->lesson($now->subHours(2));
        $meeting->forceFill(['status' => MeetingStatus::Cancelled])->save();

        $this->artisan('meetings:close-expired')->assertExitCode(0);

        $this->assertSame([], $this->meet->conferencesEnded);
    }

    /** Long-past meetings age out, so the sweep never becomes a scan of all history. */
    public function test_the_sweep_does_not_reach_back_indefinitely(): void
    {
        $now = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->lesson($now->subDays(5));

        $this->artisan('meetings:close-expired')->assertExitCode(0);

        $this->assertSame([], $this->meet->conferencesEnded);
    }
}
