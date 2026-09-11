<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Events\MeetingCreated;
use App\Enums\InstructorStatus;
use App\Enums\StudentStatus;
use App\Http\Resources\Student\StudentBookingResource;
use App\Listeners\Booking\SendMeetingNotifications;
use App\Livewire\Frontend\Student\BookingDetail;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\BookingType;
use App\Models\User;
use App\Notifications\Booking\MeetingCreatedNotification;
use App\Services\Instructor\InstructorDashboardService;
use App\Services\Student\StudentDashboardService;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The SIRI meeting join gateway: participants are handed
 * /dashboard/meetings/{booking}/join everywhere, and only that endpoint —
 * after re-checking who they are, whether the lesson is on and whether
 * the join window is open — redirects to the provider's participant URL.
 * The provider URL and the Zoom host start URL never appear in anything
 * a participant is served.
 */
final class MeetingJoinGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const string JOIN_URL = 'https://us05web.zoom.us/j/82122025909?pwd=participant';

    private const string HOST_URL = 'https://us05web.zoom.us/s/82122025909?zak=host-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);

        $settings = app(MeetingSettings::class);
        $settings->student_join_url_visible = true;
        $settings->instructor_join_url_visible = true;
        $settings->save();
    }

    /** @return array{0: Booking, 1: User, 2: User} */
    private function confirmedBookingWithMeeting(int $startsInMinutes = 10, StudentStatus $studentStatus = StudentStatus::Active): array
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->profile()->update(['student_status' => $studentStatus]);

        $instructor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $instructor->assignRole('instructor');
        $instructor->profile()->update(['instructor_status' => InstructorStatus::Active]);

        $type = BookingType::factory()->create(['key' => 'free_demo', 'duration_minutes' => 30]);

        $booking = Booking::factory()->for($type, 'type')->create([
            'student_id' => $student->id,
            'instructor_id' => $instructor->id,
            'status' => BookingStatus::Confirmed,
            'starts_at' => now()->addMinutes($startsInMinutes),
            'ends_at' => now()->addMinutes($startsInMinutes + 30),
        ]);

        BookingMeeting::factory()->zoom()->created(self::JOIN_URL)->create([
            'booking_id' => $booking->id,
            'host_url' => self::HOST_URL,
            'password' => 'participant',
            'provider_meeting_id' => '82122025909',
            'starts_at' => now()->addMinutes($startsInMinutes),
            'ends_at' => now()->addMinutes($startsInMinutes + 30),
        ]);

        return [$booking->fresh(), $student, $instructor];
    }

    private function gateway(Booking $booking): string
    {
        return route('dashboard.meetings.join', $booking);
    }

    // ── Link generation ───────────────────────────────────────────────

    public function test_the_join_link_is_a_siri_url_and_the_service_still_decides_on_the_provider_url(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $service = app(BookingMeetingServiceInterface::class);

        $link = $service->joinLinkFor($booking);

        $this->assertStringStartsWith(config('app.url'), $link);
        $this->assertStringContainsString('/dashboard/meetings/'.$booking->id.'/join', $link);
        $this->assertStringNotContainsString('zoom.us', $link);
        $this->assertSame(self::JOIN_URL, $service->participantJoinUrlFor($booking, $student));
    }

    public function test_every_participant_surface_carries_the_siri_link_and_never_the_provider_url(): void
    {
        Notification::fake();
        [$booking, $student, $instructor] = $this->confirmedBookingWithMeeting();

        // Booking detail page.
        Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->assertSee($this->gateway($booking))
            ->assertDontSee(self::JOIN_URL)
            ->assertDontSee(self::HOST_URL);

        // Student dashboard payload.
        $summary = app(StudentDashboardService::class)->summary($student->fresh());
        $this->assertSame($this->gateway($booking), $summary->nextLesson['join_url']);

        // Instructor dashboard payload.
        $instructorSummary = app(InstructorDashboardService::class)->summary($instructor->fresh());
        $this->assertSame($this->gateway($booking), collect($instructorSummary->nextLessons)->firstWhere('id', $booking->id)['join_url']);

        // API resource.
        $request = Request::create('/api/test');
        $request->setUserResolver(fn () => $student);
        $payload = (new StudentBookingResource($booking))->toArray($request);
        $this->assertSame($this->gateway($booking), $payload['meeting_url']);

        // Notifications.
        app(SendMeetingNotifications::class)->handleCreated(new MeetingCreated($booking, $booking->meeting));
        Notification::assertSentTo($student, MeetingCreatedNotification::class, function (MeetingCreatedNotification $n) use ($student, $booking): bool {
            $mail = $n->toMail($student);
            $serialized = (string) json_encode($mail).$n->toSms($student);

            return $mail->actionUrl === $this->gateway($booking)
                && ! str_contains($serialized, 'zoom.us')
                && ! str_contains($serialized, 'zak=');
        });
        Notification::assertSentTo($instructor, MeetingCreatedNotification::class, fn (MeetingCreatedNotification $n): bool => $n->toMail($instructor)->actionUrl === $this->gateway($booking));
    }

    // ── The gateway ───────────────────────────────────────────────────

    public function test_the_student_is_redirected_to_the_participant_url_inside_the_window_and_it_is_audited(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();

        $this->actingAs($student)->get($this->gateway($booking))->assertRedirect(self::JOIN_URL);

        $activity = Activity::query()->where('event', 'meeting_join_redirected')->sole();
        $this->assertSame($student->id, (int) $activity->causer_id);
        $this->assertSame('student', $activity->properties['role']);
        $this->assertStringNotContainsString('zoom.us', (string) json_encode($activity->properties));
    }

    public function test_the_instructor_is_redirected_to_the_participant_url_never_the_host_url(): void
    {
        [$booking, , $instructor] = $this->confirmedBookingWithMeeting();

        $response = $this->actingAs($instructor)->get($this->gateway($booking));

        $response->assertRedirect(self::JOIN_URL);
        $this->assertStringNotContainsString('zak=', (string) $response->headers->get('Location'));
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();

        $this->get($this->gateway($booking))->assertRedirect('/login');
    }

    public function test_a_stranger_gets_403(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();
        $stranger = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $stranger->assignRole('student');
        $stranger->profile()->update(['student_status' => StudentStatus::Active]);

        $this->actingAs($stranger)->get($this->gateway($booking))->assertForbidden();
    }

    public function test_too_early_shows_the_waiting_page_instead_of_redirecting(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting(startsInMinutes: 120);

        $this->actingAs($student)->get($this->gateway($booking))
            ->assertOk()
            ->assertSee('The lesson has not opened yet')
            ->assertDontSee(self::JOIN_URL)
            ->assertDontSee(self::HOST_URL);

        $this->assertSame(0, Activity::query()->where('event', 'meeting_join_redirected')->count());
    }

    public function test_after_the_window_the_lesson_cannot_be_joined(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting(startsInMinutes: -120);

        $this->actingAs($student)->get($this->gateway($booking))
            ->assertOk()
            ->assertSee('cannot be joined right now')
            ->assertDontSee(self::JOIN_URL);
    }

    public function test_a_cancelled_lesson_or_a_meeting_that_is_not_created_is_not_joinable(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $booking->meeting->update(['status' => MeetingStatus::Cancelled]);
        $this->actingAs($student)->get($this->gateway($booking))->assertOk()->assertDontSee(self::JOIN_URL);

        $booking->meeting->update(['status' => MeetingStatus::Created]);
        $booking->update(['status' => BookingStatus::Cancelled]);
        $this->actingAs($student)->get($this->gateway($booking))->assertOk()->assertDontSee(self::JOIN_URL);
    }

    public function test_a_suspended_student_is_refused_at_click_time_even_with_a_copied_link(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting(studentStatus: StudentStatus::Suspended);

        $response = $this->actingAs($student)->get($this->gateway($booking));

        // Whatever the portal middleware decides for a suspended student
        // (a redirect away, or the not-joinable page), the provider URL
        // is never released.
        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotSame(self::JOIN_URL, (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString(self::JOIN_URL, (string) $response->getContent());
    }

    public function test_the_instructor_visibility_setting_is_enforced_at_the_gateway(): void
    {
        [$booking, , $instructor] = $this->confirmedBookingWithMeeting();
        $settings = app(MeetingSettings::class);
        $settings->instructor_join_url_visible = false;
        $settings->save();

        $this->actingAs($instructor)->get($this->gateway($booking))->assertOk()->assertDontSee(self::JOIN_URL);
    }

    public function test_an_instructor_who_is_not_publicly_visible_is_refused(): void
    {
        [$booking, , $instructor] = $this->confirmedBookingWithMeeting();
        $instructor->profile()->update(['instructor_status' => InstructorStatus::Suspended]);

        $this->actingAs($instructor->fresh())->get($this->gateway($booking))->assertOk()->assertDontSee(self::JOIN_URL);
    }

    public function test_an_administrator_who_may_view_the_booking_is_not_redirected(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get($this->gateway($booking));

        // Admins use the admin portal; whatever the portal middleware
        // decides, the provider URL is never in the response.
        $this->assertStringNotContainsString(self::JOIN_URL, (string) $response->getContent());
        $this->assertNotSame(self::JOIN_URL, (string) $response->headers->get('Location'));
    }

    /** Google Meet bookings go through the same gateway unchanged. */
    public function test_google_meet_meetings_redirect_through_the_same_gateway(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $booking->meeting->update(['provider' => 'google_meet', 'join_url' => 'https://meet.google.com/abc-defg-hij', 'host_url' => null, 'password' => null]);

        $this->actingAs($student)->get($this->gateway($booking))->assertRedirect('https://meet.google.com/abc-defg-hij');
    }
}
