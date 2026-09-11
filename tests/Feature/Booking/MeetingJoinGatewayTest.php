<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Events\MeetingCreated;
use App\Booking\Services\MeetingJoinHandoffService;
use App\Enums\InstructorStatus;
use App\Enums\StudentStatus;
use App\Filament\Pages\Settings\MeetingSettingsPage;
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
use Illuminate\Support\Facades\Cache;
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

        $type = BookingType::query()->where('key', 'free_demo')->first()
            ?? BookingType::factory()->create(['key' => 'free_demo', 'duration_minutes' => 30]);

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

    // ── Dedicated participant host (meet.sirieducation.com) ───────────

    private const string JOIN_HOST = 'https://meet.sirieducation.test';

    private function useParticipantHost(): void
    {
        $settings = app(MeetingSettings::class);
        $settings->participant_join_base_url = self::JOIN_HOST.'/';
        $settings->save();
    }

    private function hostedJoin(Booking $booking): string
    {
        return self::JOIN_HOST.'/join/'.$booking->id;
    }

    public function test_join_links_move_to_the_participant_host_when_configured_and_app_url_is_untouched(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $service = app(BookingMeetingServiceInterface::class);

        $this->assertSame(route('dashboard.meetings.join', $booking), $service->joinLinkFor($booking), 'unset: the main host');

        $this->useParticipantHost();

        $this->assertSame($this->hostedJoin($booking), $service->joinLinkFor($booking));
        $this->assertSame('http://127.0.0.1:8000', config('app.url'), 'APP_URL is not the mechanism');
        $this->assertSame(self::JOIN_URL, $service->participantJoinUrlFor($booking, $student), 'the provider decision is unchanged');

        // Surfaces and notifications follow the setting.
        Livewire::actingAs($student)
            ->test(BookingDetail::class, ['bookingId' => $booking->id])
            ->assertSee($this->hostedJoin($booking))
            ->assertDontSee(self::JOIN_URL);
    }

    public function test_previously_sent_main_host_links_keep_working(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();

        $this->actingAs($student)->get(route('dashboard.meetings.join', $booking))->assertRedirect(self::JOIN_URL);
        $this->actingAs($student)->get('/join/'.$booking->id)->assertRedirect(self::JOIN_URL);
    }

    /** No cookie is shared across hosts: a guest on the join host is sent to the main host's handoff, not to a second login. */
    public function test_a_guest_on_the_join_host_is_sent_to_the_main_host_handoff(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();

        $this->get($this->hostedJoin($booking))
            ->assertRedirect(config('app.url').'/dashboard/meetings/'.$booking->id.'/handoff');
    }

    public function test_a_guest_on_the_main_host_goes_to_login_not_into_a_handoff_loop(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();

        $this->get('/join/'.$booking->id)->assertRedirect('/login');
    }

    public function test_the_handoff_grants_the_participant_a_booking_scoped_pass_on_the_meeting_host_once(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();

        // Main host: the existing session issues a token and sends the browser across.
        $issued = $this->actingAs($student)->get(route('dashboard.meetings.handoff', $booking));
        $issued->assertRedirect();
        $issued->assertHeader('Cache-Control', 'no-store, private');
        $issued->assertHeader('Referrer-Policy', 'no-referrer');
        $location = (string) $issued->headers->get('Location');
        $this->assertStringStartsWith($this->hostedJoin($booking).'?handoff=', $location);
        $this->assertStringNotContainsString('zoom.us', $location);
        $this->assertDatabaseHas('activity_log', ['event' => 'meeting_join_handoff_issued', 'subject_id' => $booking->id]);

        // Meeting host, as a GUEST (fresh browser state): the token becomes a
        // booking-scoped grant in THIS host's session — not a login — and is
        // dropped from the URL.
        $this->freshBrowser();
        $redeemed = $this->get($location);
        $redeemed->assertRedirect($this->hostedJoin($booking));
        $redeemed->assertHeader('Cache-Control', 'no-store, private');
        $redeemed->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertFalse(auth()->check(), 'a grant is not a web login');

        // The clean URL now runs the ordinary gateway for that user.
        $this->get($this->hostedJoin($booking))->assertRedirect(self::JOIN_URL);
        $this->assertSame(1, Activity::query()->where('event', 'meeting_join_redirected')->where('causer_id', $student->id)->count());

        // Single use: replaying the token in a fresh browser gives nothing.
        $this->freshBrowser();
        $this->get($location)->assertRedirect($this->hostedJoin($booking));
        $this->assertFalse(auth()->check());
        $this->get($this->hostedJoin($booking))->assertRedirect(config('app.url').'/dashboard/meetings/'.$booking->id.'/handoff');
    }

    /** Wipes session and auth state, as a browser with no cookies for the host would present. */
    private function freshBrowser(): void
    {
        auth()->logout();
        $this->app['session']->flush();
        $this->app['session']->regenerate();
    }

    /** Two nodes redeem the same token at once: exactly one wins. */
    public function test_concurrent_redemption_of_one_token_admits_exactly_one_request(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $handoff = app(MeetingJoinHandoffService::class);
        $token = $handoff->issue($student, $booking);
        $request = Request::create($this->hostedJoin($booking));

        // Node B arrives while node A is between its atomic "redeemed"
        // marker and reading the payload: the marker alone decides.
        $this->assertTrue(Cache::add('meeting-join-handoff:redeemed:'.$token, true, 120), 'A wins the marker');
        $this->assertNull($handoff->redeem($token, $booking, $request), 'B is refused although the payload still exists');

        // And in the ordinary sequential case the first redemption is the only one.
        $token = $handoff->issue($student, $booking);
        $this->assertNotNull($handoff->redeem($token, $booking, $request));
        $this->assertNull($handoff->redeem($token, $booking, $request));
    }

    public function test_a_token_cannot_be_redeemed_on_any_host_but_the_configured_https_meeting_origin(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $handoff = app(MeetingJoinHandoffService::class);

        foreach ([
            config('app.url').'/join/'.$booking->id,                 // main host
            'https://evil.example.test/join/'.$booking->id,          // another host
            'http://meet.sirieducation.test/join/'.$booking->id,     // right host, plain HTTP
        ] as $wrong) {
            $token = $handoff->issue($student, $booking);
            $this->assertNull($handoff->redeem($token, $booking, Request::create($wrong)), $wrong);
            $this->assertNull($handoff->redeem($token, $booking, Request::create($this->hostedJoin($booking))), 'consumed by the failed attempt: '.$wrong);
        }

        // Over HTTP, the token is dropped and no grant is stored.
        $token = $handoff->issue($student, $booking);
        $this->freshBrowser();
        $this->get('http://meet.sirieducation.test/join/'.$booking->id.'?handoff='.$token)->assertRedirect('http://meet.sirieducation.test/join/'.$booking->id);
        $this->get($this->hostedJoin($booking))->assertRedirect(config('app.url').'/dashboard/meetings/'.$booking->id.'/handoff');
    }

    /** A grant for one booking opens nothing else. */
    public function test_a_grant_is_scoped_to_its_booking(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        [$other] = $this->confirmedBookingWithMeeting();
        $other->update(['student_id' => $student->id]);
        $this->useParticipantHost();
        $token = app(MeetingJoinHandoffService::class)->issue($student, $booking);

        $this->freshBrowser();
        $this->get($this->hostedJoin($booking).'?handoff='.$token);
        $this->get($this->hostedJoin($booking))->assertRedirect(self::JOIN_URL);

        // Same session, the student's OTHER lesson: no grant, so back to the main host.
        $this->get($this->hostedJoin($other))->assertRedirect(config('app.url').'/dashboard/meetings/'.$other->id.'/handoff');
    }

    /** A grant is not a login: dashboard and account routes on the meeting host still see a guest. */
    public function test_a_grant_does_not_authenticate_dashboard_or_account_routes(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $token = app(MeetingJoinHandoffService::class)->issue($student, $booking);

        $this->freshBrowser();
        $this->get($this->hostedJoin($booking).'?handoff='.$token);
        $this->get($this->hostedJoin($booking))->assertRedirect(self::JOIN_URL);

        $this->assertFalse(auth()->check());
        $this->get(self::JOIN_HOST.'/dashboard')->assertRedirect(self::JOIN_HOST.'/login');
        $this->get(self::JOIN_HOST.'/dashboard/my-bookings')->assertRedirect(self::JOIN_HOST.'/login');
        $this->get(self::JOIN_HOST.'/dashboard/meetings/'.$booking->id.'/join')->assertRedirect(self::JOIN_HOST.'/login');
    }

    /** A token never replaces the identity of someone already signed in on the meeting host. */
    public function test_a_token_is_discarded_when_another_user_is_already_signed_in_on_the_meeting_host(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other->assignRole('student');
        $other->profile()->update(['student_status' => StudentStatus::Active]);
        $token = app(MeetingJoinHandoffService::class)->issue($student, $booking);

        $this->actingAs($other)->get($this->hostedJoin($booking).'?handoff='.$token)->assertRedirect($this->hostedJoin($booking));

        $this->assertSame($other->id, auth()->id(), 'the signed-in identity is untouched');
        $this->assertNull(app(MeetingJoinHandoffService::class)->grantedUser($this->app['session']->driver(), $booking), 'no grant was stored for the token\'s user');
        $this->get($this->hostedJoin($booking))->assertForbidden();
    }

    public function test_a_handoff_token_expires_and_is_bound_to_its_booking(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        [$other] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $handoff = app(MeetingJoinHandoffService::class);
        $request = Request::create($this->hostedJoin($booking));

        // Wrong booking: refused and consumed.
        $token = $handoff->issue($student, $booking);
        $this->assertNull($handoff->redeem($token, $other, $request));
        $this->assertNull($handoff->redeem($token, $booking, $request), 'a failed attempt still burns the token');

        // Expired.
        $token = $handoff->issue($student, $booking);
        $this->travel(MeetingJoinHandoffService::TOKEN_TTL_SECONDS + 1)->seconds();
        $this->assertNull($handoff->redeem($token, $booking, $request));

        // Garbage.
        $this->assertNull($handoff->redeem('', $booking, $request));
        $this->assertNull($handoff->redeem(str_repeat('a', 200), $booking, $request));
        $this->assertNull($handoff->redeem(str_repeat('!', 48), $booking, $request));
    }

    /** The handoff signs in; it grants nothing. A stranger holding a token for a booking that is not theirs still meets the gateway. */
    public function test_a_handoff_never_bypasses_the_gateway(): void
    {
        [$booking] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();
        $stranger = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $stranger->assignRole('student');
        $stranger->profile()->update(['student_status' => StudentStatus::Active]);

        // The stranger cannot even obtain a token for this booking on the main host.
        $this->actingAs($stranger)->get(route('dashboard.meetings.handoff', $booking))->assertForbidden();

        // And a token minted for them anyway grants them the pass, then the gateway refuses.
        $token = app(MeetingJoinHandoffService::class)->issue($stranger, $booking);
        $this->freshBrowser();
        $this->get($this->hostedJoin($booking).'?handoff='.$token)->assertRedirect($this->hostedJoin($booking));
        $response = $this->get($this->hostedJoin($booking));
        $response->assertForbidden();
        $this->assertStringNotContainsString(self::JOIN_URL, (string) $response->getContent());
        $this->assertStringNotContainsString(self::HOST_URL, (string) $response->getContent());
    }

    public function test_the_join_link_domain_must_be_a_bare_https_origin(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        foreach ([
            'http://meet.sirieducation.test',
            'https://meet.sirieducation.test/join',
            'https://meet.sirieducation.test/?x=1',
            'https://meet.sirieducation.test/#frag',
            'https://user:pw@meet.sirieducation.test',
            'meet.sirieducation.test',
        ] as $bad) {
            Livewire::actingAs($admin)
                ->test(MeetingSettingsPage::class)
                ->fillForm(['participant_join_base_url' => $bad])
                ->call('save')
                ->assertHasFormErrors(['participant_join_base_url']);
            $this->assertNull(MeetingJoinHandoffService::normalizeOrigin($bad), $bad);
        }

        $this->assertSame('https://meet.sirieducation.test', MeetingJoinHandoffService::normalizeOrigin('https://MEET.sirieducation.test/'));
        $this->assertSame('https://meet.sirieducation.test:8443', MeetingJoinHandoffService::normalizeOrigin('https://meet.sirieducation.test:8443'));
        $this->assertNull(app(MeetingSettings::class)->refresh()->participant_join_base_url);

        // An unusable stored value never produces a broken link.
        $settings = app(MeetingSettings::class);
        $settings->participant_join_base_url = 'http://meet.sirieducation.test';
        $settings->save();
        [$booking] = $this->confirmedBookingWithMeeting();
        $this->assertSame(route('dashboard.meetings.join', $booking), app(BookingMeetingServiceInterface::class)->joinLinkFor($booking));
    }

    public function test_the_handoff_endpoint_is_only_served_on_the_main_host(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();
        $this->useParticipantHost();

        $this->actingAs($student)->get(self::JOIN_HOST.'/dashboard/meetings/'.$booking->id.'/handoff')->assertNotFound();
    }

    public function test_without_a_meeting_origin_the_handoff_just_returns_to_the_main_host_link(): void
    {
        [$booking, $student] = $this->confirmedBookingWithMeeting();

        $this->actingAs($student)->get(route('dashboard.meetings.handoff', $booking))
            ->assertRedirect(route('dashboard.meetings.join', $booking));
        $this->assertDatabaseMissing('activity_log', ['event' => 'meeting_join_handoff_issued']);
    }

    public function test_the_settings_page_stores_the_join_link_domain_without_a_trailing_slash(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(MeetingSettingsPage::class)
            ->fillForm(['participant_join_base_url' => 'https://meet.sirieducation.test/'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://meet.sirieducation.test', app(MeetingSettings::class)->refresh()->participant_join_base_url);
    }
}
