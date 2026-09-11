<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\DTOs\CancelBookingData;
use App\Booking\DTOs\RescheduleBookingData;
use App\Booking\Enums\BookingActor;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingHostReservationStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\MeetingHostCapacityException;
use App\Booking\Meetings\GoogleCalendarMeetProvider;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Services\ZoomHostCapacityPreflightService;
use App\Filament\Pages\Settings\MeetingSettingsPage;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\MeetingHostReservation;
use App\Models\User;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsZoomHostCapacityFixtures;
use Tests\TestCase;

/**
 * Turning Zoom host capacity reservation OFF and ON safely.
 *
 * Off is a kill switch for NEW Zoom commitments, never a licence to
 * accept them unreserved: Zoom-bound acceptance is refused, existing
 * reservations keep being honoured/moved/released, existing Zoom
 * meetings are untouched, and provider pins survive. On is gated: it
 * cannot be enabled while any upcoming Zoom-bound booking holds no
 * reservation, and Zoom cannot become the default while it is off.
 */
final class MeetingHostCapacityRolloutTest extends TestCase
{
    use BuildsZoomHostCapacityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootZoomHostCapacityFixtures();
    }

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function switchReservation(bool $on): void
    {
        $settings = app(MeetingSettings::class);
        $settings->zoom_host_capacity_enabled = $on;
        $settings->save();
    }

    /** A booking accepted before the feature existed: Zoom-bound, no reservation, no pin. */
    private function legacyZoomBooking(int $hour = 10): Booking
    {
        $this->switchReservation(false);
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);
        $booking = $this->demo($this->teacherA, $this->slot(3, $hour));
        Booking::query()->whereKey($booking->id)->update(['meeting_provider_intent' => ZoomMeetingProvider::KEY]);
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false);

        return $booking->fresh();
    }

    // ── Rollback: new acceptance ──────────────────────────────────────

    public function test_with_reservation_off_a_zoom_bound_booking_is_refused_rather_than_accepted_unreserved(): void
    {
        $this->switchReservation(false);

        try {
            $this->demo($this->teacherA, $this->slot());
            $this->fail('A Zoom-bound booking must not be accepted without capacity.');
        } catch (MeetingHostCapacityException $e) {
            $this->assertStringContainsString('not being accepted right now', $e->getMessage());
        }

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, MeetingHostReservation::query()->count());
    }

    public function test_with_reservation_off_google_meet_bookings_are_accepted_normally(): void
    {
        $this->switchReservation(false);
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);

        $booking = $this->demo($this->teacherA, $this->slot());

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->meeting_provider_intent);
    }

    // ── Rollback: existing holds, pins, meetings, reschedules ─────────

    public function test_an_existing_hold_keeps_its_reservation_and_is_released_normally_after_rollback(): void
    {
        $hold = $this->paid($this->teacherA, $this->slot());
        $this->assertNotNull($this->activeReservationFor($hold));

        $this->switchReservation(false);

        // Still occupying the host — and still released by the normal path.
        Booking::query()->whereKey($hold->id)->update(['reserved_until' => now()->subMinute()]);
        $this->artisan('booking:release-expired')->assertSuccessful();

        $this->assertSame(BookingStatus::Cancelled, $hold->fresh()->status);
        $this->assertSame(MeetingHostReservationStatus::Released, MeetingHostReservation::query()->where('booking_id', $hold->id)->sole()->status);
    }

    public function test_payment_confirmation_after_rollback_keeps_the_hold_reservation(): void
    {
        $hold = $this->paid($this->teacherA, $this->slot());
        $this->switchReservation(false);

        $confirmed = $this->settlePayment($hold);

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
        $this->assertSame(ZoomMeetingProvider::KEY, $confirmed->meeting_provider_intent, 'the pin survives');
        $this->assertNull($this->activeReservationFor($confirmed)?->expires_at, 'the hold became a confirmed reservation');
    }

    public function test_a_reserved_booking_still_gets_its_zoom_meeting_after_rollback_on_its_reserved_host(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->switchReservation(false);
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: true);

        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh());

        $this->assertSame(MeetingStatus::Created, $meeting?->status);
        $this->assertSame(ZoomMeetingProvider::KEY, $meeting?->provider);
        $this->assertSame($this->activeReservationFor($booking)?->platform_meeting_host_id, $meeting?->platform_meeting_host_id);
    }

    public function test_a_pinned_zoom_booking_without_a_reservation_gets_no_meeting_while_reservation_is_off(): void
    {
        $legacy = $this->legacyZoomBooking();
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: true);

        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($legacy);

        $this->assertSame(MeetingStatus::Failed, $meeting?->status);
        $this->assertStringContainsString('not being accepted right now', (string) $meeting?->failure_reason);
        $this->assertSame([], $this->zoom->created, 'Zoom was never asked');
    }

    public function test_rescheduling_a_reserved_booking_after_rollback_still_enforces_capacity(): void
    {
        $mine = $this->demo($this->teacherA, $this->slot(3, 10));
        $theirs = $this->demo($this->teacherB, $this->slot(3, 14), $this->makeStudent());
        $this->switchReservation(false);

        try {
            app(BookingServiceInterface::class)->reschedule($mine, new RescheduleBookingData(startsAt: $this->slot(3, 14), actor: BookingActor::Admin));
            $this->fail('The taken hour must still be refused.');
        } catch (MeetingHostCapacityException) {
            // expected
        }

        $moved = app(BookingServiceInterface::class)->reschedule($mine->fresh(), new RescheduleBookingData(startsAt: $this->slot(3, 16), actor: BookingActor::Admin));

        $this->assertTrue($moved->starts_at->equalTo($this->slot(3, 16)));
        $this->assertSame(1, MeetingHostReservation::query()->active()->where('booking_id', $mine->id)->count(), 'the reservation followed the booking');
        $this->assertNotNull($this->activeReservationFor($theirs));
    }

    public function test_rescheduling_an_unreserved_zoom_booking_is_refused_while_reservation_is_off(): void
    {
        $legacy = $this->legacyZoomBooking();

        $this->expectException(MeetingHostCapacityException::class);
        $this->expectExceptionMessage('not being accepted right now');

        app(BookingServiceInterface::class)->reschedule($legacy, new RescheduleBookingData(startsAt: $this->slot(3, 16), actor: BookingActor::Admin));
    }

    public function test_cancellation_after_rollback_releases_and_preserves_history(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->switchReservation(false);

        app(BookingServiceInterface::class)->cancel($booking, new CancelBookingData(cancelledBy: BookingActor::Admin, reason: 'rollback test'));

        $row = MeetingHostReservation::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingHostReservationStatus::Released, $row->status);
        $this->assertSame(ZoomMeetingProvider::KEY, $row->provider);
    }

    // ── Legacy bookings at confirmation ───────────────────────────────

    /**
     * Existing payment reconciliation semantics are preserved: a verified
     * payment confirms the booking even when no capacity can be found.
     * What changes is that the shortfall is explicit — audited — and the
     * booking never gets a Zoom meeting until capacity exists.
     */
    public function test_a_legacy_pinned_booking_confirmed_without_capacity_is_audited_and_gets_no_zoom_meeting(): void
    {
        // Pending-payment hold accepted for Google (no reservation), later pinned to Zoom.
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);
        $legacy = $this->paid($this->teacherA, $this->slot());
        Booking::query()->whereKey($legacy->id)->update(['meeting_provider_intent' => ZoomMeetingProvider::KEY]);
        $this->assertSame(0, $this->activeReservations());

        // Someone else holds the host for that hour now.
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false);
        $this->demo($this->teacherB, $this->slot(), $this->makeStudent());

        $confirmed = $this->settlePayment($legacy->fresh());

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status, 'the payment still confirms the lesson');
        $this->assertNull($this->activeReservationFor($confirmed));
        $this->assertDatabaseHas('activity_log', ['event' => 'meeting_host_capacity_unreserved']);

        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true);
        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($confirmed->fresh());
        $this->assertSame(MeetingStatus::Failed, $meeting?->status);
        $this->assertStringContainsString('No Zoom host is available', (string) $meeting?->failure_reason);
        $this->assertSame([], $this->zoom->created);
    }

    // ── Activation gate ───────────────────────────────────────────────

    public function test_reservation_cannot_be_enabled_while_an_upcoming_zoom_booking_holds_no_reservation(): void
    {
        $legacy = $this->legacyZoomBooking();
        $this->assertFalse(app(MeetingSettings::class)->zoom_host_capacity_enabled);
        $this->actingAs($this->superAdmin());

        Livewire::test(MeetingSettingsPage::class)
            ->set('data.default_provider', GoogleCalendarMeetProvider::KEY)
            ->set('data.zoom_host_capacity_enabled', true)
            ->call('save')
            ->assertNotified('Zoom host capacity reservation not enabled');

        $this->assertFalse(app(MeetingSettings::class)->refresh()->zoom_host_capacity_enabled, 'the switch stayed off');
        $this->assertSame(0, MeetingHostReservation::query()->count(), 'the gate reserves nothing');
        $this->assertSame(BookingStatus::Confirmed, $legacy->fresh()->status);
    }

    public function test_zoom_cannot_become_the_default_while_reservation_is_off(): void
    {
        $this->switchReservation(false);
        $settings = app(MeetingSettings::class);
        $settings->default_provider = GoogleCalendarMeetProvider::KEY;
        $settings->save();
        $this->actingAs($this->superAdmin());

        Livewire::test(MeetingSettingsPage::class)
            ->set('data.default_provider', ZoomMeetingProvider::KEY)
            ->set('data.zoom_host_capacity_enabled', false)
            ->call('save')
            ->assertNotified('Meeting settings not saved');

        $this->assertSame(GoogleCalendarMeetProvider::KEY, app(MeetingSettings::class)->refresh()->default_provider);
    }

    public function test_reservation_can_be_enabled_once_the_preflight_is_clean(): void
    {
        $this->switchReservation(false);
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);
        $this->actingAs($this->superAdmin());

        Livewire::test(MeetingSettingsPage::class)
            ->set('data.zoom_host_capacity_enabled', true)
            ->call('save')
            ->assertNotified('Meeting settings saved');

        $this->assertTrue(app(MeetingSettings::class)->refresh()->zoom_host_capacity_enabled);
    }

    // ── Preflight coverage and backfill ───────────────────────────────

    public function test_preflight_counts_unpinned_bookings_when_zoom_is_the_default_and_ignores_those_served_by_google(): void
    {
        // Accepted for Google before any of this; one already has a created Google meeting.
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);
        $unpinned = $this->demo($this->teacherA, $this->slot(3, 10));
        $served = $this->demo($this->teacherB, $this->slot(3, 12), $this->makeStudent());
        Booking::query()->whereIn('id', [$unpinned->id, $served->id])->update(['meeting_provider_intent' => null]);
        BookingMeeting::factory()->google()->created()->create(['booking_id' => $served->id]);

        // Zoom becomes the default (as it would at cutover): the unpinned
        // booking would be routed to Zoom, the Google-served one would not.
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false);
        $report = app(ZoomHostCapacityPreflightService::class)->report();

        $this->assertSame([$unpinned->reference], $report->unallocated->pluck('reference')->all());
        $this->assertTrue($report->hasFindings());
    }

    public function test_backfill_reserves_what_fits_reports_what_does_not_and_pins_legacy_zoom_meetings(): void
    {
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false);
        $reserved = $this->demo($this->teacherA, $this->slot(3, 10)); // holds the host at 10:00

        // Two legacy Zoom-bound bookings without reservations: one at a free hour
        // (with a pre-existing Zoom meeting created before hosts existed), one clashing.
        $this->configureZoomDefault(capacityEnabled: false, autoCreate: false, defaultProvider: GoogleCalendarMeetProvider::KEY);
        $free = $this->demo($this->teacherB, $this->slot(3, 14), $this->makeStudent());
        $clash = $this->demo($this->makeTeacher(), $this->slot(3, 10), $this->makeStudent());
        Booking::query()->whereIn('id', [$free->id, $clash->id])->update(['meeting_provider_intent' => ZoomMeetingProvider::KEY]);
        BookingMeeting::factory()->zoom()->created('https://zoom.us/j/legacy')->create(['booking_id' => $free->id, 'provider_meeting_id' => 'legacy-1', 'platform_meeting_host_id' => null]);

        // Dry run writes nothing.
        Artisan::call('meetings:zoom-hosts:backfill');
        $this->assertSame(1, MeetingHostReservation::query()->active()->count());

        $exit = Artisan::call('meetings:zoom-hosts:backfill', ['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit, 'an unplaceable booking is a finding');
        $this->assertStringContainsString('reserved       '.$free->reference, $output);
        $this->assertStringContainsString('NOT placed', $output);
        $this->assertStringContainsString($clash->reference, $output);
        $this->assertNotNull($this->activeReservationFor($free));
        $this->assertNull($this->activeReservationFor($clash));
        $this->assertNotNull($this->activeReservationFor($reserved));
        $this->assertSame(
            $this->activeReservationFor($free)?->platform_meeting_host_id,
            BookingMeeting::query()->where('booking_id', $free->id)->sole()->platform_meeting_host_id,
            'the legacy Zoom meeting is pinned to the host it was reserved on',
        );
        $this->assertDatabaseHas('activity_log', ['event' => 'meeting_host_capacity_backfilled']);
        $this->assertSame(BookingStatus::Confirmed, $clash->fresh()->status, 'nothing is rewritten for the operator');
    }

    public function test_the_resolve_command_shows_state_and_refuses_without_an_administrator(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot());

        $this->artisan('meetings:resolve-ambiguous', ['booking' => $booking->reference])
            ->expectsOutputToContain('Remote state unknown')
            ->assertSuccessful();

        $this->artisan('meetings:resolve-ambiguous', ['booking' => $booking->reference, '--none' => true, '--reason' => 'x'])
            ->assertFailed();
    }
}
