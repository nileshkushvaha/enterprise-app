<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\BookingException;
use App\Booking\Exceptions\GatewayAmbiguousRequestException;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Services\BookingMeetingService;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsZoomHostCapacityFixtures;
use Tests\TestCase;

/**
 * Phase 3 closure audit, point A1–A3: an administrator's meeting id is
 * a claim, not proof. Every adoption is checked against what Zoom
 * itself reports about the meeting before anything is written, and the
 * whole ambiguity story — the unanswered create, every reconciliation,
 * who resolved it, why and when — is kept, never overwritten.
 */
final class MeetingAdoptionSafetyTest extends TestCase
{
    use BuildsZoomHostCapacityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootZoomHostCapacityFixtures();
    }

    /** The per-kind auto-create toggle, switched on only AFTER a booking exists so the listener never pre-creates the meeting. */
    private function allowExplicitCreates(): void
    {
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /** A booking whose first create went unanswered; the fake did NOT create anything remotely. */
    private function ambiguousBooking(): Booking
    {
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->allowExplicitCreates();
        $this->zoom->throwOnCreate = new GatewayAmbiguousRequestException('did not complete');
        $this->zoom->createRemotelyOnAmbiguousFailure = false;
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        $this->assertTrue(BookingMeeting::query()->where('booking_id', $booking->id)->sole()->metadata[BookingMeetingService::META_REMOTE_STATE_UNKNOWN]);

        return $booking->fresh();
    }

    /** @param  array<string, mixed>  $overrides */
    private function remoteMeeting(string $id, Booking $for, array $overrides = []): void
    {
        $this->zoom->remoteMeetings[$id] = [
            'id' => $id,
            'host_id' => 'platform-zoom-host',
            'host_email' => null,
            'agenda' => "Booking reference: {$for->reference}\nDuration: 30 minutes",
            'start_time' => $for->starts_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ...$overrides,
        ];
    }

    private function adopt(Booking $booking, string $id): BookingMeeting
    {
        return app(BookingMeetingServiceInterface::class)->adoptRemoteMeeting($booking, $id, $this->admin());
    }

    // ── A1/A2: the id is verified, not trusted ────────────────────────

    public function test_a_verified_meeting_is_adopted_and_the_evidence_recorded(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000111', $booking);

        $meeting = $this->adopt($booking, '555000111');

        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $this->assertSame('555000111', $meeting->provider_meeting_id);
        $this->assertSame(['createMeeting', 'inspectMeeting', 'updateMeeting'], $this->zoom->calls, 'read and check first, write second');
        $adopted = collect($meeting->metadata[BookingMeetingService::META_AMBIGUITY_HISTORY])->firstWhere('event', 'adopted');
        $this->assertSame('platform-zoom-host', $adopted['evidence']['host_matched']);
        $this->assertSame($booking->reference, $adopted['evidence']['agenda_reference']);
    }

    public function test_an_id_zoom_does_not_know_is_refused_before_anything_is_written(): void
    {
        $booking = $this->ambiguousBooking();

        try {
            $this->adopt($booking, '404404404');
            $this->fail('expected refusal');
        } catch (BookingException $e) {
            $this->assertStringContainsString('does not exist at the provider', $e->getMessage());
        }

        $this->assertNotContains('updateMeeting', $this->zoom->calls, 'no remote write');
        $this->assertTrue(BookingMeeting::query()->where('booking_id', $booking->id)->sole()->metadata[BookingMeetingService::META_REMOTE_STATE_UNKNOWN], 'still unresolved');
    }

    public function test_a_meeting_hosted_by_another_zoom_user_is_refused(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000222', $booking, ['host_id' => 'someone-elses-user', 'host_email' => 'other@example.com']);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('not hosted by the platform host');

        try {
            $this->adopt($booking, '555000222');
        } finally {
            $this->assertNotContains('updateMeeting', $this->zoom->calls);
        }
    }

    /** The host may be reported by email rather than user id; either identity of the platform host is accepted. */
    public function test_the_platform_host_is_recognised_by_email_too(): void
    {
        $this->registerHost('platform@sirieducation.example');
        $booking = $this->ambiguousBooking();
        // Reserved on whichever host had room first — pin the check to the email host by making it the reservation host.
        $reservation = $this->activeReservationFor($booking);
        $hostReference = $reservation->host->host_reference;
        $this->remoteMeeting('555000333', $booking, ['host_id' => null, 'host_email' => strtoupper($hostReference)]);

        $meeting = $this->adopt($booking, '555000333');

        $this->assertSame(MeetingStatus::Created, $meeting->status);
    }

    public function test_a_meeting_that_reports_no_host_is_refused(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000444', $booking, ['host_id' => null, 'host_email' => null]);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('reports no host');
        $this->adopt($booking, '555000444');
    }

    public function test_a_meeting_already_owned_by_another_booking_is_refused(): void
    {
        $other = $this->demo($this->teacherB, $this->slot(4), $this->makeStudent());
        $this->allowExplicitCreates();
        $otherMeeting = app(BookingMeetingServiceInterface::class)->createMeeting($other->fresh(), ZoomMeetingProvider::KEY);
        $this->assertSame(MeetingStatus::Created, $otherMeeting?->status);
        $ownedId = (string) $otherMeeting->provider_meeting_id;

        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false); // so the next booking is not pre-created by the listener
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting($ownedId, $booking); // even with a perfectly matching agenda …

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage(sprintf('already belongs to booking %s', $other->reference));
        $this->adopt($booking, $ownedId);
    }

    public function test_a_meeting_whose_agenda_names_a_different_booking_is_refused(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000555', $booking, ['agenda' => "Booking reference: BK-SOMEONEELSE\nDuration: 30 minutes"]);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('was created for booking BK-SOMEONEELSE');
        $this->adopt($booking, '555000555');
    }

    public function test_a_meeting_with_no_reference_far_from_the_lesson_time_is_refused(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000666', $booking, [
            'agenda' => 'Some other meeting',
            'start_time' => $booking->starts_at->utc()->addDays(3)->format('Y-m-d\TH:i:s\Z'),
        ]);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('carries no booking reference and starts');
        $this->adopt($booking, '555000666');
    }

    /** No reference but the right host at the right time: allowed (the ambiguous create may have lost its agenda), and the evidence says so. */
    public function test_a_meeting_with_no_reference_at_the_lesson_time_on_the_platform_host_is_adoptable(): void
    {
        $booking = $this->ambiguousBooking();
        $this->remoteMeeting('555000777', $booking, ['agenda' => null]);

        $meeting = $this->adopt($booking, '555000777');

        $this->assertSame(MeetingStatus::Created, $meeting->status);
        $adopted = collect($meeting->metadata[BookingMeetingService::META_AMBIGUITY_HISTORY])->firstWhere('event', 'adopted');
        $this->assertNull($adopted['evidence']['agenda_reference']);
        $this->assertSame($booking->starts_at->utc()->toIso8601String(), $adopted['evidence']['remote_starts_at']);
    }

    /** Automatic reconciliation applies the same ownership rule before adopting its single match. */
    public function test_automatic_reconciliation_never_adopts_a_meeting_another_booking_owns(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->allowExplicitCreates();
        $this->zoom->throwOnCreate = new GatewayAmbiguousRequestException('did not complete');
        $this->zoom->createRemotelyOnAmbiguousFailure = true;
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        $remoteId = (string) (900000000 + 0);

        // Another local row already claims that remote id.
        $other = Booking::factory()->confirmed()->create(['meeting_provider_intent' => ZoomMeetingProvider::KEY]);
        BookingMeeting::factory()->zoom()->created()->create(['booking_id' => $other->id, 'provider_meeting_id' => $remoteId]);

        $meeting = app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);

        $this->assertSame(MeetingStatus::Failed, $meeting?->status);
        $this->assertStringContainsString('already belongs to booking', (string) $meeting?->failure_reason);
        $this->assertNotContains('updateMeeting', $this->zoom->calls);
        $this->assertTrue($meeting?->metadata[BookingMeetingService::META_REMOTE_STATE_UNKNOWN] ?? false, 'still flagged for a person to look at');
    }

    // ── A3: evidence is kept, never deleted ───────────────────────────

    public function test_declaring_none_keeps_the_full_ambiguity_history(): void
    {
        $booking = $this->ambiguousBooking();
        // Two reconciliation attempts, both inconclusive-none.
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);

        $admin = $this->admin();
        $row = app(BookingMeetingServiceInterface::class)->acknowledgeNoRemoteMeeting($booking->fresh(), $admin, 'Checked the Zoom web portal: no meeting for this reference.');

        $history = $row->metadata[BookingMeetingService::META_AMBIGUITY_HISTORY];
        $events = array_column($history, 'event');
        $this->assertSame(['ambiguous_create', 'reconciliation', 'reconciliation', 'resolved_no_remote_meeting'], $events);
        $this->assertSame('none', $history[1]['status']);
        $this->assertTrue($history[1]['exhaustive']);
        $this->assertSame($admin->id, $history[3]['by']);
        $this->assertStringContainsString('Zoom web portal', $history[3]['reason']);
        $this->assertNotEmpty($history[3]['at']);
        $this->assertNotEmpty($history[0]['at']);
        $this->assertFalse($row->metadata[BookingMeetingService::META_REMOTE_STATE_UNKNOWN]);
        $this->assertSame('none', $row->metadata[BookingMeetingService::META_RECONCILIATION]['status'], 'last reconciliation kept');
        $this->assertSame('no_remote_meeting', $row->metadata[BookingMeetingService::META_AMBIGUITY_RESOLUTION]['outcome']);

        // The fresh create that follows carries the history forward too.
        $created = app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        $this->assertSame(MeetingStatus::Created, $created?->status);
        $this->assertSame($events, array_column($created->metadata[BookingMeetingService::META_AMBIGUITY_HISTORY], 'event'), 'history survives the successful create');
    }

    public function test_adoption_keeps_the_history_and_appends_the_resolution(): void
    {
        $booking = $this->ambiguousBooking();
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        $this->remoteMeeting('555000888', $booking);

        $meeting = $this->adopt($booking, '555000888');

        $events = array_column($meeting->metadata[BookingMeetingService::META_AMBIGUITY_HISTORY], 'event');
        $this->assertSame(['ambiguous_create', 'reconciliation', 'adopted'], $events);
        $this->assertArrayNotHasKey(BookingMeetingService::META_REMOTE_STATE_UNKNOWN, $meeting->metadata);
    }

    // ── Operator command ──────────────────────────────────────────────

    public function test_the_resolve_command_refuses_an_unverifiable_id_and_reports_why(): void
    {
        $booking = $this->ambiguousBooking();
        $admin = $this->admin();
        $this->remoteMeeting('555000999', $booking, ['host_id' => 'not-ours']);

        $exit = Artisan::call('meetings:resolve-ambiguous', ['booking' => $booking->reference, '--adopt' => '555000999', '--admin' => (string) $admin->id]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not hosted by the platform host', Artisan::output());
        $this->assertSame(MeetingStatus::Failed, BookingMeeting::query()->where('booking_id', $booking->id)->sole()->status);
    }

    public function test_the_check_auth_command_reports_state_without_creating_a_meeting_or_printing_secrets(): void
    {
        $exit = Artisan::call('meetings:zoom:check-auth');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('OAuth token acquired', $output);
        $this->assertStringContainsString('configured (decryptable)', $output);
        $this->assertStringNotContainsString('zoom_test_client_secret_value', $output);
        $this->assertSame(['validateCredentials'], $this->zoom->calls, 'one token mint, no meeting');
    }
}
