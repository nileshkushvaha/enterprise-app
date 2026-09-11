<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\DTOs\MeetingUpdateContext;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\MeetingHostCapacityException;
use App\Booking\Exceptions\MeetingProviderSwitchNotSupportedException;
use App\Booking\Meetings\GoogleCalendarMeetProvider;
use App\Booking\Meetings\ManualMeetingProvider;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\User;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsZoomHostCapacityFixtures;
use Tests\TestCase;

/**
 * Live-canary audit: a booking that already has a CREATED Google Meet
 * meeting must never be silently switched to Zoom — and must never be
 * REPORTED as switched either. The supported canary route is to pin a
 * booking to Zoom before its meeting exists.
 */
final class MeetingProviderSwitchTest extends TestCase
{
    use BuildsZoomHostCapacityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootZoomHostCapacityFixtures();
        // The production shape: Google Meet is the default, meetings are
        // created automatically on confirmation, Zoom is configured and
        // capacity reservation is on with one registered host.
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true, defaultProvider: ManualMeetingProvider::KEY);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /**
     * The production shape under audit: a confirmed booking whose Google
     * Meet meeting already exists (created row, live join link). Built
     * with automatic creation off so the row is exactly what the Google
     * provider would have persisted, then automatic creation is restored.
     */
    private function bookingWithCreatedGoogleMeeting(int $daysAhead = 3): Booking
    {
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false, defaultProvider: ManualMeetingProvider::KEY);
        $booking = $this->demo($this->teacherA, $this->slot($daysAhead));
        BookingMeeting::factory()->google()->created('https://meet.google.com/abc-defg-hij')->create([
            'booking_id' => $booking->id,
            'provider_event_id' => 'evt_live',
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
        ]);
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true, defaultProvider: ManualMeetingProvider::KEY);

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $meeting->provider);

        return $booking->fresh();
    }

    // ── Switching a created meeting is refused, loudly ────────────────

    public function test_asking_for_zoom_on_a_booking_with_a_created_meeting_is_refused_not_reported_as_success(): void
    {
        $booking = $this->bookingWithCreatedGoogleMeeting();
        $originalJoinUrl = $booking->meeting->join_url;

        try {
            app(BookingMeetingServiceInterface::class)->createMeeting($booking, ZoomMeetingProvider::KEY);
            $this->fail('A provider switch on a created meeting must be refused explicitly.');
        } catch (MeetingProviderSwitchNotSupportedException $e) {
            $this->assertStringContainsString('already has a created google_meet meeting', $e->getMessage());
            $this->assertStringContainsString('meetings:pin-provider', $e->getMessage());
        }

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $meeting->provider, 'the existing meeting is untouched');
        $this->assertSame($originalJoinUrl, $meeting->join_url, 'participants keep their link');
        $this->assertSame([], $this->zoom->created, 'Zoom was never asked');
        $this->assertSame(0, $this->activeReservations(), 'no host capacity was consumed');
    }

    /** The automatic path (no explicit provider) stays a silent, idempotent no-op — a redelivered listener must not throw. */
    public function test_the_automatic_path_still_returns_the_existing_created_meeting_silently(): void
    {
        $booking = $this->bookingWithCreatedGoogleMeeting();

        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($booking);

        $this->assertSame(MeetingStatus::Created, $meeting?->status);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $meeting?->provider);
    }

    /** Re-choosing the SAME provider is the ordinary idempotent retry and still succeeds. */
    public function test_re_choosing_the_same_provider_on_a_created_meeting_is_not_a_switch(): void
    {
        $booking = $this->bookingWithCreatedGoogleMeeting();

        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($booking, GoogleCalendarMeetProvider::KEY);

        $this->assertSame(MeetingStatus::Created, $meeting?->status);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $meeting?->provider);
    }

    public function test_the_manual_path_refuses_to_overwrite_a_created_meeting_on_another_provider(): void
    {
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true, defaultProvider: ZoomMeetingProvider::KEY);
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->assertSame(ZoomMeetingProvider::KEY, $booking->fresh()->meeting->provider);

        $this->expectException(MeetingProviderSwitchNotSupportedException::class);
        app(BookingMeetingServiceInterface::class)->saveManualMeeting($booking->fresh(), new MeetingUpdateContext(joinUrl: 'https://example.test/manual'));
    }

    // ── The supported canary route: pin BEFORE the meeting exists ─────

    public function test_a_pending_payment_booking_can_be_pinned_to_zoom_and_gets_a_zoom_meeting_on_confirmation(): void
    {
        $booking = $this->paid($this->teacherA, $this->slot());
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull(BookingMeeting::query()->where('booking_id', $booking->id)->first(), 'no meeting before payment');
        $this->assertSame(0, $this->activeReservations());

        $pinned = app(BookingMeetingServiceInterface::class)->pinProvider($booking, ZoomMeetingProvider::KEY, $this->admin());

        $this->assertSame(ZoomMeetingProvider::KEY, $pinned->meeting_provider_intent);
        $this->assertNotNull($this->activeReservationFor($booking), 'capacity is held from the moment of the pin');
        $this->assertTrue($this->activeReservationFor($booking)->expires_at?->equalTo($booking->reserved_until), 'the hold\'s expiry travels with it');
        $this->assertDatabaseHas('activity_log', ['event' => 'meeting_provider_pinned']);

        // Payment settles → BookingConfirmed → the listener creates the meeting — on Zoom, not the default.
        $this->settlePayment($booking);

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(ZoomMeetingProvider::KEY, $meeting->provider);
        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertSame('platform-zoom-host', $this->zoom->created[0]['hostUser']);
        $this->assertTrue($this->zoom->created[0]['payload']['settings']['join_before_host'], 'the hostless payload');
        $this->assertNull($this->activeReservationFor($booking)->expires_at, 'confirmation cleared the hold expiry');
        $this->assertSame(ManualMeetingProvider::KEY, app(MeetingSettings::class)->default_provider, 'the platform default never moved');
    }

    public function test_pinning_is_refused_when_the_host_has_no_room(): void
    {
        $blocker = $this->paid($this->teacherB, $this->slot());
        app(BookingMeetingServiceInterface::class)->pinProvider($blocker, ZoomMeetingProvider::KEY, $this->admin());

        $booking = $this->paid($this->teacherA, $this->slot());

        try {
            app(BookingMeetingServiceInterface::class)->pinProvider($booking, ZoomMeetingProvider::KEY, $this->admin());
            $this->fail('expected capacity refusal');
        } catch (MeetingHostCapacityException) {
            // expected
        }

        $this->assertSame(ManualMeetingProvider::KEY, $booking->fresh()->meeting_provider_intent, 'the pin rolled back with the failed reservation — the acceptance-time pin stands');
    }

    public function test_pinning_is_refused_while_new_zoom_reservations_are_switched_off(): void
    {
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: true, defaultProvider: ManualMeetingProvider::KEY);
        $booking = $this->paid($this->teacherA, $this->slot());

        $this->expectException(MeetingHostCapacityException::class);
        $this->expectExceptionMessage('capacity reservation is switched off');
        app(BookingMeetingServiceInterface::class)->pinProvider($booking, ZoomMeetingProvider::KEY, $this->admin());
    }

    public function test_pinning_a_booking_whose_meeting_already_exists_on_another_provider_is_refused(): void
    {
        $booking = $this->bookingWithCreatedGoogleMeeting();

        $this->expectException(MeetingProviderSwitchNotSupportedException::class);
        app(BookingMeetingServiceInterface::class)->pinProvider($booking, ZoomMeetingProvider::KEY, $this->admin());
    }

    public function test_pinning_back_to_the_default_releases_the_zoom_reservation(): void
    {
        $booking = $this->paid($this->teacherA, $this->slot());
        $admin = $this->admin();
        app(BookingMeetingServiceInterface::class)->pinProvider($booking, ZoomMeetingProvider::KEY, $admin);
        $this->assertSame(1, $this->activeReservations());

        app(BookingMeetingServiceInterface::class)->pinProvider($booking->fresh(), ManualMeetingProvider::KEY, $admin);

        $this->assertSame(ManualMeetingProvider::KEY, $booking->fresh()->meeting_provider_intent);
        $this->assertSame(0, $this->activeReservations());
    }

    public function test_the_pin_command_reports_success_and_refusals(): void
    {
        $admin = $this->admin();
        $booking = $this->paid($this->teacherA, $this->slot());

        $this->artisan('meetings:pin-provider', ['booking' => $booking->reference, '--provider' => 'zoom', '--admin' => (string) $admin->id])
            ->expectsOutputToContain('is pinned to zoom')
            ->assertExitCode(0);
        $this->assertSame(ZoomMeetingProvider::KEY, $booking->fresh()->meeting_provider_intent);

        $created = $this->bookingWithCreatedGoogleMeeting(daysAhead: 4);

        $this->artisan('meetings:pin-provider', ['booking' => $created->reference, '--provider' => 'zoom', '--admin' => (string) $admin->id])
            ->expectsOutputToContain('Switching it to zoom is not supported')
            ->assertExitCode(1);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $created->fresh()->meeting->provider, 'untouched');
    }

    /** The refusal message names the providers involved, so the operator knows exactly what stands. */
    public function test_the_refusal_names_both_providers(): void
    {
        $booking = $this->bookingWithCreatedGoogleMeeting();

        $this->expectException(MeetingProviderSwitchNotSupportedException::class);
        $this->expectExceptionMessage('already has a created google_meet meeting. Switching it to zoom is not supported');
        app(BookingMeetingServiceInterface::class)->createMeeting($booking, ZoomMeetingProvider::KEY);
    }
}
