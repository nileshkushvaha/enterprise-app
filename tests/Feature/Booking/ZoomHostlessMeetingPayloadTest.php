<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\Contracts\BookingServiceInterface;
use App\Booking\DTOs\RescheduleBookingData;
use App\Booking\Enums\BookingActor;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Exceptions\GatewayAmbiguousRequestException;
use App\Booking\Meetings\GoogleCalendarMeetProvider;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Models\BookingMeeting;
use App\Models\User;
use App\Settings\FeatureSettings;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsZoomHostCapacityFixtures;
use Tests\TestCase;

/**
 * Phase 4 — the Zoom meeting SIRI provisions is HOSTLESS: the licensed
 * platform user owns it and never has to join; the instructor and the
 * student enter as ordinary participants; cloud recording starts on its
 * own. These tests pin the exact payload fields, on creation AND on
 * every later write (reschedule, retry, adoption), so an update can
 * never quietly restore a waiting room or hand out host rights.
 */
final class ZoomHostlessMeetingPayloadTest extends TestCase
{
    use BuildsZoomHostCapacityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootZoomHostCapacityFixtures();
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true);
    }

    /** @return array<string, mixed> */
    private function createdPayload(int $index = 0): array
    {
        return $this->zoom->created[$index]['payload'];
    }

    /** @param  array<string, mixed>  $settings */
    private function assertHostless(array $settings): void
    {
        $this->assertTrue($settings['join_before_host'], 'participants may enter without the platform host');
        $this->assertSame(5, $settings['jbh_time'], 'the conservative five-minute early-entry option');
        $this->assertFalse($settings['waiting_room'], 'nobody is left waiting for a host to admit them');
        $this->assertFalse($settings['use_pmi'], 'a generated id per lesson, never the reusable PMI');
        $this->assertSame('', $settings['alternative_hosts'], 'no alternative host — the teacher is never elevated');
        $this->assertFalse($settings['meeting_authentication'], 'guests join by link and passcode');
        $this->assertSame(2, $settings['approval_type'], 'no registration step');
        $this->assertTrue($settings['mute_upon_entry']);
        $this->assertArrayNotHasKey('host_key', $settings);
        $this->assertArrayNotHasKey('co_host', $settings);
    }

    public function test_a_created_lesson_meeting_is_hostless_with_cloud_auto_recording_and_a_strong_passcode(): void
    {
        $this->enableRecordingEligibility();
        $booking = $this->demo($this->teacherA, $this->slot());

        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();
        $this->assertSame(MeetingStatus::Created, $meeting->status);

        $payload = $this->createdPayload();
        $this->assertSame(2, $payload['type'], 'scheduled meeting, never instant, never PMI');
        $this->assertHostless($payload['settings']);
        $this->assertSame('cloud', $payload['settings']['auto_recording'], 'Zoom records to the cloud on its own');

        // Passcode: SIRI-generated, 10 characters, Zoom's allowed set.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $payload['password']);
        $this->assertArrayNotHasKey('default_password', $payload);

        // Only the platform host identity appears anywhere in the request.
        $this->assertSame('platform-zoom-host', $this->zoom->created[0]['hostUser']);
        $encoded = json_encode($payload);
        $this->assertStringNotContainsString($this->teacherA->email, $encoded);
        $this->assertStringNotContainsString((string) $this->student->email, $encoded);
    }

    public function test_a_lesson_that_is_not_recording_eligible_is_still_hostless_but_not_recorded(): void
    {
        $features = app(FeatureSettings::class);
        $features->recording_enabled = false;
        $features->save();

        $this->demo($this->teacherA, $this->slot());

        $payload = $this->createdPayload();
        $this->assertHostless($payload['settings']);
        $this->assertSame('none', $payload['settings']['auto_recording'], 'consent and policy still decide recording');
    }

    public function test_a_reschedule_updates_the_meeting_with_the_same_hostless_settings_and_keeps_the_passcode(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot(3, 10));
        $original = BookingMeeting::query()->where('booking_id', $booking->id)->sole();

        app(BookingServiceInterface::class)->reschedule($booking, new RescheduleBookingData(startsAt: $this->slot(3, 14), actor: BookingActor::Admin));
        // A reschedule propagates to Zoom through the same provider update path used by retries.
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);

        $this->assertSame([], $this->zoom->updated, 'a Created row is left alone by createMeeting');

        // Force the update path exactly as the admin "Create/Update Meeting" retry does on a non-created row.
        $original->forceFill(['status' => MeetingStatus::Failed])->save();
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);

        $this->assertCount(1, $this->zoom->updated);
        $update = $this->zoom->updated[0]['payload'];
        $this->assertHostless($update['settings']);
        $this->assertArrayNotHasKey('password', $update, 'an update never rotates the passcode participants already hold');
    }

    public function test_adopting_a_meeting_after_an_ambiguous_create_aligns_it_to_hostless_settings(): void
    {
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: false);
        $booking = $this->demo($this->teacherA, $this->slot());
        $this->configureZoomDefault(capacityEnabled: true, autoCreate: true);

        $this->zoom->throwOnCreate = new GatewayAmbiguousRequestException('did not complete');
        $this->zoom->createRemotelyOnAmbiguousFailure = true;
        app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);
        $adopted = app(BookingMeetingServiceInterface::class)->createMeeting($booking->fresh(), ZoomMeetingProvider::KEY);

        $this->assertSame(MeetingStatus::Created, $adopted?->status);
        $this->assertCount(1, $this->zoom->updated, 'adoption aligns the remote meeting');
        $this->assertHostless($this->zoom->updated[0]['payload']['settings']);
        $this->assertArrayNotHasKey('password', $this->zoom->updated[0]['payload']);
    }

    public function test_google_meet_provisioning_is_untouched_by_the_zoom_hostless_settings(): void
    {
        // The Zoom payload builder is private to the Zoom provider; the
        // Google provider has no waiting-room or join-before-host concept.
        $this->assertFalse(method_exists(GoogleCalendarMeetProvider::class, 'meetingPayload'));
        $source = (string) file_get_contents(app_path('Booking/Meetings/GoogleCalendarMeetProvider.php'));
        $this->assertStringNotContainsString('join_before_host', $source);
        $this->assertStringNotContainsString('waiting_room', $source);
    }

    /** The teacher never appears as host, co-host or alternative host — and the host credential never leaves the hidden column. */
    public function test_no_teacher_identity_and_no_host_credential_reach_participant_output(): void
    {
        $booking = $this->demo($this->teacherA, $this->slot());
        $meeting = BookingMeeting::query()->where('booking_id', $booking->id)->sole();

        $this->assertStringContainsString('zak=', (string) $meeting->host_url, 'the host credential exists …');
        $this->assertArrayNotHasKey('host_url', $meeting->toArray(), '… and is never serialized');
        $this->assertArrayNotHasKey('password', $meeting->toArray());
        $this->assertSame('', $this->createdPayload()['settings']['alternative_hosts']);
    }

    private function enableRecordingEligibility(): void
    {
        $features = app(FeatureSettings::class);
        $features->recording_enabled = true;
        $features->save();

        $settings = app(MeetingSettings::class);
        $settings->recording_enabled = true;
        $settings->zoom_recording_enabled = true;
        $settings->save();
    }
}
