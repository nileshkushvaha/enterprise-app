<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingMeetingServiceInterface;
use App\Booking\DTOs\MeetingUpdateContext;
use App\Booking\DTOs\RecordingProviderReconciliation;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Enums\RecordingStatus;
use App\Booking\Exceptions\BookingException;
use App\Booking\Jobs\CaptureLessonRecordingJob;
use App\Booking\Meetings\FakeMeetingProvider;
use App\Booking\Meetings\GoogleCalendarMeetProvider;
use App\Booking\Registry\MeetingProviderRegistry;
use App\Booking\Services\RecordingService;
use App\Booking\Services\RecordingStagingArea;
use App\Booking\Storage\FilesystemRecordingStorage;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingMeeting;
use App\Models\Recording;
use App\Models\User;
use App\Settings\FeatureSettings;
use App\Settings\MeetingSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\InMemoryRecordingStorage;
use Tests\TestCase;

/**
 * Production 11 Sep 2026, booking BK-FROYGKWF1F: a Google Meet meeting
 * was created (recording registered), cancelled, and a Zoom meeting was
 * created on the SAME booking_meetings row. The recording row kept
 * provider=google_meet while its meeting said zoom, and the capture job
 * picks its adapter from the recording — so it would have asked Google
 * for a Zoom recording, forever.
 *
 * The fix re-points a row under which nothing was captured, and refuses
 * to touch one that is in flight or stored. Both are audited.
 */
final class RecordingProviderReplacementTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryRecordingStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        FakeMeetingProvider::reset();

        $this->storage = new InMemoryRecordingStorage;
        $this->app->instance(InMemoryRecordingStorage::class, $this->storage);
        config([
            'recordings.storage_driver' => InMemoryRecordingStorage::KEY,
            'recordings.drivers' => [
                InMemoryRecordingStorage::KEY => InMemoryRecordingStorage::class,
                FilesystemRecordingStorage::KEY => FilesystemRecordingStorage::class,
            ],
        ]);

        $features = app(FeatureSettings::class);
        $features->recording_enabled = true;
        $features->save();

        $meetings = app(MeetingSettings::class);
        $meetings->recording_enabled = true;
        $meetings->save();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app(RecordingStagingArea::class)->path());

        parent::tearDown();
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    /**
     * The production shape after the replacement: the meeting row is
     * now a created meeting on the (fake) new provider; the recording
     * still names google_meet.
     */
    private function replacedMeetingWithStaleRecording(RecordingStatus $status = RecordingStatus::Pending, array $extra = []): Recording
    {
        $recording = Recording::factory()->create([
            'provider' => GoogleCalendarMeetProvider::KEY,
            'provider_reference' => null,
            'status' => $status,
            'capture_attempts' => 0,
            ...$extra,
        ]);
        $recording->bookingMeeting->update([
            'provider' => FakeMeetingProvider::KEY,
            'status' => MeetingStatus::Created,
            'provider_meeting_id' => '82122025909',
            'ends_at' => now()->subHours(2),
            'starts_at' => now()->subHours(3),
        ]);
        // Eligibility needs active, consenting participants on a confirmed lesson.
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $instructor = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $recording->booking->update(['status' => BookingStatus::Confirmed, 'student_id' => $student->id, 'instructor_id' => $instructor->id]);
        // Real rows are keyed on their meeting id (RecordingService::registerIfEligible).
        $recording->update(['student_id' => $student->id, 'teacher_id' => $instructor->id, 'idempotency_key' => 'recording:'.$recording->booking_meeting_id]);

        return $recording->fresh();
    }

    private function fakeMp4Bytes(): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 200);
    }

    private function runCaptureJob(Recording $recording): void
    {
        (new CaptureLessonRecordingJob($recording->id))->handle(app(RecordingService::class), app(MeetingProviderRegistry::class));
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    // ── Registration on the replaced meeting ──────────────────────────

    public function test_registering_the_recording_for_a_replaced_meeting_re_points_a_pending_row(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();

        $registered = app(RecordingService::class)->registerIfEligible(
            $stale->booking,
            $stale->bookingMeeting,
            app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY),
        );

        $this->assertNotNull($registered);
        $this->assertSame($stale->id, $registered->id, 'the idempotency key still maps to one row');
        $this->assertSame(FakeMeetingProvider::KEY, $registered->provider);
        $this->assertSame(RecordingStatus::Pending, $registered->status);
        $this->assertSame(0, $registered->capture_attempts);
        $this->assertSame(1, Recording::query()->count());
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_provider_realigned', 'subject_id' => $stale->id]);
    }

    public function test_a_failed_row_with_nothing_stored_is_re_pointed_and_gets_a_fresh_attempt_budget(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording(RecordingStatus::Failed, [
            'capture_attempts' => 5,
            'failure_code' => 'capture_retries_exhausted',
            'failed_at' => now()->subHour(),
            'provider_reference' => 'google-artifact-123',
        ]);

        $outcome = app(RecordingService::class)->reconcileProvider($stale);

        $this->assertSame(RecordingProviderReconciliation::REALIGNED, $outcome->decision);
        $fresh = $stale->fresh();
        $this->assertSame(FakeMeetingProvider::KEY, $fresh->provider);
        $this->assertSame(RecordingStatus::Pending, $fresh->status);
        $this->assertSame(0, $fresh->capture_attempts);
        $this->assertNull($fresh->failure_code);
        $this->assertNull($fresh->provider_reference, 'the old provider\'s artifact id means nothing to the new one');
    }

    // ── Stale queued job ──────────────────────────────────────────────

    /** The job queued for the Google meeting runs after the switch to Zoom: it captures from the meeting's provider, not the stale one. */
    public function test_a_stale_capture_job_re_points_the_row_and_captures_from_the_meetings_provider(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$nextRecordingReference = 'fake-ref-after-switch';

        $this->runCaptureJob($stale);

        $fresh = $stale->fresh();
        $this->assertSame(FakeMeetingProvider::KEY, $fresh->provider);
        $this->assertSame(RecordingStatus::Available, $fresh->status);
        $this->assertSame('fake-ref-after-switch', $fresh->provider_reference);
        $this->assertSame(1, $fresh->capture_attempts);
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_provider_realigned', 'subject_id' => $stale->id]);
    }

    /** Idempotent: a redelivered stale job after the first one settled the row is a no-op. */
    public function test_a_redelivered_stale_job_after_capture_changes_nothing(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$nextRecordingReference = 'fake-ref-1';
        $this->runCaptureJob($stale);
        $before = $stale->fresh()->only(['status', 'provider', 'storage_path', 'capture_attempts']);

        $this->runCaptureJob($stale);

        $this->assertSame($before, $stale->fresh()->only(['status', 'provider', 'storage_path', 'capture_attempts']));
        $this->assertSame(1, Recording::query()->count());
    }

    // ── Protected rows ────────────────────────────────────────────────

    public function test_a_stored_recording_is_never_relabelled_and_a_stale_job_spends_no_attempt(): void
    {
        $stored = $this->replacedMeetingWithStaleRecording(RecordingStatus::Stored, [
            'storage_driver' => InMemoryRecordingStorage::KEY,
            'storage_path' => 'recordings/2026/09/lesson-google.mp4',
            'size_bytes' => 1234,
            'capture_attempts' => 1,
            'provider_reference' => 'google-artifact-1',
        ]);
        $before = $stored->only(['provider', 'status', 'storage_driver', 'storage_path', 'provider_reference', 'capture_attempts']);

        $outcome = app(RecordingService::class)->reconcileProvider($stored);
        $this->assertSame(RecordingProviderReconciliation::PROTECTED, $outcome->decision);

        $this->runCaptureJob($stored);

        $this->assertSame($before, $stored->fresh()->only(['provider', 'status', 'storage_driver', 'storage_path', 'provider_reference', 'capture_attempts']));
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_provider_mismatch_retained', 'subject_id' => $stored->id]);
        $this->assertDatabaseMissing('activity_log', ['event' => 'recording_provider_realigned', 'subject_id' => $stored->id]);
    }

    public function test_an_in_flight_transfer_is_never_touched(): void
    {
        $inFlight = $this->replacedMeetingWithStaleRecording(RecordingStatus::Transferring, [
            'transfer_started_at' => now()->subMinutes(5),
            'capture_attempts' => 1,
        ]);

        $outcome = app(RecordingService::class)->reconcileProvider($inFlight, audit: false);
        $this->runCaptureJob($inFlight);

        $this->assertSame(RecordingProviderReconciliation::PROTECTED, $outcome->decision);
        $fresh = $inFlight->fresh();
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $fresh->provider);
        $this->assertSame(RecordingStatus::Transferring, $fresh->status);
        $this->assertSame(1, $fresh->capture_attempts);
    }

    /** Ingestion refuses to claim a row whose provider disagrees with its meeting, even if handed the "right" adapter for the stale provider. */
    public function test_ingestion_refuses_a_provider_mismatch_without_spending_an_attempt(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        // Meeting says fake, recording says google: fetching with the
        // fake adapter directly (bypassing the job's reconciliation)
        // must be refused — the row does not name that provider.
        app(RecordingService::class)->capture($stale, app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY));

        $fresh = $stale->fresh();
        $this->assertSame(RecordingStatus::Pending, $fresh->status);
        $this->assertSame(0, $fresh->capture_attempts);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $fresh->provider);
    }

    public function test_a_row_whose_meeting_is_not_created_is_left_alone(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $stale->bookingMeeting->update(['status' => MeetingStatus::Cancelled]);

        $outcome = app(RecordingService::class)->reconcileProvider($stale->fresh(), audit: false);

        $this->assertSame(RecordingProviderReconciliation::PROTECTED, $outcome->decision);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
    }

    public function test_an_aligned_row_is_reported_as_aligned_and_unchanged(): void
    {
        $recording = Recording::factory()->create(['provider' => FakeMeetingProvider::KEY]);
        $recording->bookingMeeting->update(['provider' => FakeMeetingProvider::KEY, 'status' => MeetingStatus::Created]);

        $outcome = app(RecordingService::class)->reconcileProvider($recording->fresh());

        $this->assertSame(RecordingProviderReconciliation::ALIGNED, $outcome->decision);
        $this->assertSame(0, Activity::query()->whereIn('event', ['recording_provider_realigned', 'recording_provider_mismatch_retained'])->count());
    }

    // ── Operator recovery command ─────────────────────────────────────

    public function test_the_recovery_command_previews_without_changing_anything(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();

        $this->artisan('recordings:reconcile-provider', ['recording' => $stale->id])
            ->expectsOutputToContain('Preview only')
            ->expectsOutputToContain('re-point to '.FakeMeetingProvider::KEY)
            ->assertSuccessful();

        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
        $this->assertSame(0, Activity::query()->where('event', 'recording_provider_realigned')->count());
    }

    public function test_the_recovery_command_requires_an_administrator_to_execute(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();

        $this->artisan('recordings:reconcile-provider', ['recording' => $stale->id, '--execute' => true])
            ->assertFailed();

        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
    }

    public function test_the_recovery_command_re_points_the_row_with_an_audited_administrator(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $admin = $this->admin();

        $this->artisan('recordings:reconcile-provider', ['recording' => $stale->id, '--execute' => true, '--admin' => $admin->email])
            ->expectsOutputToContain('re-pointed from google_meet to fake')
            ->assertSuccessful();

        $fresh = $stale->fresh();
        $this->assertSame(FakeMeetingProvider::KEY, $fresh->provider);
        $this->assertSame(RecordingStatus::Pending, $fresh->status);

        $activity = Activity::query()->where('event', 'recording_provider_realigned')->sole();
        $this->assertSame($admin->id, (int) $activity->causer_id);
        $this->assertSame('google_meet', $activity->properties['previous_provider']);
    }

    public function test_the_recovery_command_refuses_a_stored_row(): void
    {
        $stored = $this->replacedMeetingWithStaleRecording(RecordingStatus::Stored, [
            'storage_driver' => InMemoryRecordingStorage::KEY,
            'storage_path' => 'recordings/2026/09/lesson-google.mp4',
            'size_bytes' => 1234,
        ]);

        $this->artisan('recordings:reconcile-provider', ['recording' => $stored->id, '--execute' => true, '--admin' => $this->admin()->email])
            ->expectsOutputToContain('Protected')
            ->assertFailed();

        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stored->fresh()->provider);
        $this->assertSame('recordings/2026/09/lesson-google.mp4', $stored->fresh()->storage_path);
    }

    /** Sanity: a normal Google → Zoom replacement through the service also re-points when the row is untouched. */
    public function test_replacement_leaves_exactly_one_recording_row_per_booking(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $booking = Booking::query()->findOrFail($stale->booking_id);

        app(RecordingService::class)->registerIfEligible($booking, BookingMeeting::query()->findOrFail($stale->booking_meeting_id), app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY));
        app(RecordingService::class)->registerIfEligible($booking, BookingMeeting::query()->findOrFail($stale->booking_meeting_id), app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY));

        $this->assertSame(1, Recording::query()->where('booking_id', $booking->id)->count());
    }

    // ── Eligibility is re-evaluated on the destination provider ───────

    public function test_recovery_is_refused_when_the_lesson_is_not_eligible_on_the_new_provider(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $stale->booking->instructor->profile()->update(['consents_to_recording' => false]);

        $outcome = app(RecordingService::class)->reconcileProvider($stale->fresh());

        $this->assertSame(RecordingProviderReconciliation::PROTECTED, $outcome->decision);
        $this->assertStringContainsString('instructor_consent_missing', (string) $outcome->reason);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_provider_mismatch_retained', 'subject_id' => $stale->id]);

        // The stale job takes the same decision and captures nothing.
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        $this->runCaptureJob($stale->fresh());
        $this->assertSame(RecordingStatus::Pending, $stale->fresh()->status);
        $this->assertSame(0, $stale->fresh()->capture_attempts);
    }

    public function test_recovery_is_refused_when_the_destination_provider_is_not_registered(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $stale->bookingMeeting->update(['provider' => 'nonexistent_provider']);

        $outcome = app(RecordingService::class)->reconcileProvider($stale->fresh(), audit: false);

        $this->assertSame(RecordingProviderReconciliation::PROTECTED, $outcome->decision);
        $this->assertStringContainsString('not registered', (string) $outcome->reason);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
    }

    public function test_re_pointing_records_the_re_evaluated_consent_beside_the_original_snapshot(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $original = $stale->consent_snapshot;

        app(RecordingService::class)->reconcileProvider($stale);

        $snapshot = $stale->fresh()->consent_snapshot;
        foreach ($original as $key => $value) {
            $this->assertSame($value, $snapshot[$key], 'the original evidence is preserved');
        }
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $snapshot['realigned']['from_provider']);
        $this->assertSame(FakeMeetingProvider::KEY, $snapshot['realigned']['to_provider']);
        $this->assertTrue($snapshot['realigned']['student_consented']);
        $this->assertTrue($snapshot['realigned']['instructor_consented']);
        $this->assertNotEmpty($snapshot['realigned']['at']);
    }

    // ── Operator authorization ────────────────────────────────────────

    public function test_the_recovery_command_refuses_an_operator_without_the_retry_permission(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $manager->assignRole('manager');

        $this->artisan('recordings:reconcile-provider', ['recording' => $stale->id, '--admin' => $manager->email])
            ->expectsOutputToContain('NOT authorized')
            ->assertSuccessful();

        $this->artisan('recordings:reconcile-provider', ['recording' => $stale->id, '--execute' => true, '--admin' => $manager->email])
            ->expectsOutputToContain('may not repair recordings')
            ->assertFailed();

        $this->assertSame(GoogleCalendarMeetProvider::KEY, $stale->fresh()->provider);
        $this->assertSame(0, Activity::query()->where('event', 'recording_provider_realigned')->count());
    }

    public function test_the_service_itself_authorizes_the_operator(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $nobody = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->expectException(AuthorizationException::class);
        app(RecordingService::class)->reconcileProvider($stale, admin: $nobody);
    }

    // ── The claim validates the live meeting under lock ───────────────

    /** A replacement that lands between the job's reconciliation and its claim is caught at the claim. */
    public function test_the_claim_refuses_when_the_meeting_moved_again_after_reconciliation(): void
    {
        $stale = $this->replacedMeetingWithStaleRecording();
        $outcome = app(RecordingService::class)->reconcileProvider($stale, audit: false);
        $this->assertSame(RecordingProviderReconciliation::REALIGNED, $outcome->decision);

        // The meeting is replaced yet again before the fetch starts.
        $stale->bookingMeeting->update(['provider' => 'manual']);
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();

        app(RecordingService::class)->capture($stale->fresh(), app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY));

        $fresh = $stale->fresh();
        $this->assertSame(RecordingStatus::Pending, $fresh->status);
        $this->assertSame(0, $fresh->capture_attempts, 'no attempt is spent: the provider was never asked');
    }

    public function test_the_claim_refuses_a_cancelled_meeting(): void
    {
        $recording = Recording::factory()->create(['provider' => FakeMeetingProvider::KEY]);
        $recording->bookingMeeting->update(['status' => MeetingStatus::Cancelled]);
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();

        app(RecordingService::class)->capture($recording->fresh(), app(MeetingProviderRegistry::class)->get(FakeMeetingProvider::KEY));

        $this->assertSame(RecordingStatus::Pending, $recording->fresh()->status);
        $this->assertSame(0, $recording->fresh()->capture_attempts);
    }

    /** A Google object stored before the switch is never re-verified and published as the Zoom lesson's recording. */
    public function test_a_stored_object_under_the_old_provider_is_never_published_for_the_new_meeting(): void
    {
        $stored = $this->replacedMeetingWithStaleRecording(RecordingStatus::Stored, [
            'storage_driver' => InMemoryRecordingStorage::KEY,
            'storage_path' => 'recordings/2026/09/lesson-google.mp4',
            'size_bytes' => 1234,
            'capture_attempts' => 1,
        ]);
        // Even the OLD provider's own adapter cannot resume it: the meeting is no longer a Google meeting.
        app(RecordingService::class)->capture($stored, app(MeetingProviderRegistry::class)->get(GoogleCalendarMeetProvider::KEY));
        $this->runCaptureJob($stored);

        $fresh = $stored->fresh();
        $this->assertSame(RecordingStatus::Stored, $fresh->status);
        $this->assertSame(GoogleCalendarMeetProvider::KEY, $fresh->provider);
        $this->assertNull($fresh->available_at);
        $this->assertSame(1, $fresh->capture_attempts);
    }

    // ── Replacement racing an in-flight capture ───────────────────────

    /** A recording of the fake provider's meeting, claimable now, whose remote id is known. */
    private function capturableRecording(): Recording
    {
        $recording = Recording::factory()->create(['provider' => FakeMeetingProvider::KEY, 'provider_reference' => null]);
        // The factory gives the meeting its own booking; production rows
        // share one. Point the meeting at the recording's booking so
        // BookingMeetingService::findForBooking() sees it.
        $recording->bookingMeeting->update([
            'booking_id' => $recording->booking_id,
            'status' => MeetingStatus::Created,
            'provider_meeting_id' => 'fake-meeting-1',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(2),
        ]);
        $recording->booking->update(['status' => BookingStatus::Confirmed]);

        return $recording->fresh();
    }

    /** Replacement lands after the claim and before discovery/fetch: nothing captured for the old meeting is published for the new one. */
    public function test_a_replacement_after_the_claim_but_before_discovery_is_refused_at_publication_and_the_object_is_kept(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        // The window: the row is Transferring, no transaction is open, the
        // provider has not yet been asked. The meeting moves to Zoom.
        FakeMeetingProvider::$onFetchRecording = function (BookingMeeting $meeting): void {
            BookingMeeting::query()->whereKey($meeting->getKey())->update(['provider' => 'zoom', 'provider_meeting_id' => '82122025909']);
        };

        $this->runCaptureJob($recording);

        $fresh = $recording->fresh();
        $this->assertSame(RecordingStatus::Failed, $fresh->status);
        $this->assertSame('meeting_replaced_during_capture', $fresh->failure_code->value);
        $this->assertNull($fresh->available_at, 'never published');
        $this->assertNotNull($fresh->storage_path, 'the uploaded object is kept under its locator');
        $this->assertNotNull($this->storage->storedName($fresh->storage_path), 'and still exists in storage');
        $this->assertSame(FakeMeetingProvider::KEY, $fresh->provider, 'the row still says what it captured');
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_failed', 'subject_id' => $recording->id]);
    }

    /** Replacement lands during the transfer, before publication — the same refusal, because publication re-checks under lock. */
    public function test_a_replacement_during_transfer_before_publication_is_refused(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$onFetchRecording = function (BookingMeeting $meeting): void {
            // Replaced on another provider while bytes are moving.
            BookingMeeting::query()->whereKey($meeting->getKey())->update(['provider' => 'google_meet', 'provider_meeting_id' => null, 'provider_event_id' => 'evt_new']);
        };

        $this->runCaptureJob($recording);

        $fresh = $recording->fresh();
        $this->assertSame(RecordingStatus::Failed, $fresh->status);
        $this->assertSame('meeting_replaced_during_capture', $fresh->failure_code->value);
        $this->assertNotNull($fresh->storage_path);

        // A later job cannot resurrect it as the new meeting's recording:
        // the row is Failed (permanent) and its provider disagrees with
        // the meeting, so the claim never happens.
        $this->runCaptureJob($fresh);
        $this->assertSame(RecordingStatus::Failed, $fresh->fresh()->status);
        $this->assertNull($fresh->fresh()->available_at);
    }

    /** Same provider, new remote meeting id: identity, not just provider, is what publication validates. */
    public function test_a_same_provider_recreation_with_a_new_remote_id_is_refused(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$onFetchRecording = function (BookingMeeting $meeting): void {
            BookingMeeting::query()->whereKey($meeting->getKey())->update(['provider_meeting_id' => 'fake-meeting-2']);
        };

        $this->runCaptureJob($recording);

        $fresh = $recording->fresh();
        $this->assertSame(RecordingStatus::Failed, $fresh->status);
        $this->assertSame('meeting_replaced_during_capture', $fresh->failure_code->value);
        $this->assertNotNull($fresh->storage_path);
        $this->assertNull($fresh->available_at);
    }

    /** Cancelling the meeting after the lesson ran is not a replacement: same identifiers, the recording publishes. */
    public function test_a_cancellation_that_keeps_the_remote_id_does_not_block_publication(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$onFetchRecording = function (BookingMeeting $meeting): void {
            BookingMeeting::query()->whereKey($meeting->getKey())->update(['status' => MeetingStatus::Cancelled]);
        };

        $this->runCaptureJob($recording);

        $this->assertSame(RecordingStatus::Available, $recording->fresh()->status);
    }

    public function test_an_unchanged_meeting_publishes_normally(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();

        $this->runCaptureJob($recording);

        $this->assertSame(RecordingStatus::Available, $recording->fresh()->status);
    }

    // ── The replacement guard ─────────────────────────────────────────

    public function test_a_replacement_meeting_is_refused_while_a_capture_is_in_flight(): void
    {
        $recording = $this->capturableRecording();
        $recording->update(['status' => RecordingStatus::Transferring, 'transfer_started_at' => now()]);
        $meeting = $recording->bookingMeeting;
        $meeting->update(['status' => MeetingStatus::Cancelled]);
        $before = $meeting->fresh()->only(['provider', 'provider_meeting_id', 'status']);

        // The booking must be eligible for a meeting, or nothing would be
        // replaced in the first place (payment NotRequired → the demo timing setting).
        $settings = app(MeetingSettings::class);
        $settings->create_after_demo_booking_confirmation = true;
        $settings->meetings_enabled = true;
        $settings->manual_provider_enabled = true;
        $settings->save();
        $booking = $recording->booking->fresh();
        $this->assertTrue(app(BookingMeetingServiceInterface::class)->isEligible($booking), 'fixture: the booking must be eligible for a replacement meeting');

        try {
            app(BookingMeetingServiceInterface::class)->createMeeting($booking, FakeMeetingProvider::KEY);
            $this->fail('A replacement must be refused while the previous meeting\'s recording is in flight.');
        } catch (BookingException $e) {
            $this->assertStringContainsString('still being captured', $e->getMessage());
        }

        try {
            app(BookingMeetingServiceInterface::class)->saveManualMeeting($booking, new MeetingUpdateContext(joinUrl: 'https://rooms.example.test/x'));
            $this->fail('The manual path is guarded too.');
        } catch (BookingException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $meeting->fresh()->only(['provider', 'provider_meeting_id', 'status']));

        // Once the capture has settled, the replacement proceeds.
        $recording->update(['status' => RecordingStatus::Failed, 'failure_code' => 'capture_retries_exhausted', 'transfer_started_at' => null]);
        $replacement = app(BookingMeetingServiceInterface::class)->saveManualMeeting($booking->fresh(), new MeetingUpdateContext(joinUrl: 'https://rooms.example.test/x'));
        $this->assertSame(MeetingStatus::Created, $replacement?->status);
    }

    // ── Admin retry never overwrites a preserved object ───────────────

    /** Same-provider replacement mid-capture, then an admin retry: refused under the lock, nothing changes, nothing is queued. */
    public function test_admin_retry_is_refused_after_a_same_provider_replacement_and_the_preserved_object_pointer_is_unchanged(): void
    {
        $recording = $this->capturableRecording();
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();
        FakeMeetingProvider::$onFetchRecording = function (BookingMeeting $meeting): void {
            BookingMeeting::query()->whereKey($meeting->getKey())->update(['provider_meeting_id' => 'fake-meeting-2']);
        };
        $this->runCaptureJob($recording);

        $before = $recording->fresh()->only(['status', 'failure_code', 'storage_driver', 'storage_path', 'storage_checksum', 'size_bytes', 'provider', 'capture_attempts', 'consent_snapshot']);
        $this->assertSame('meeting_replaced_during_capture', $before['failure_code']->value);
        $this->assertNotNull($before['storage_path']);

        Queue::fake();
        $admin = $this->admin();

        $this->assertNotNull(app(RecordingService::class)->retryRefusalReason($recording->fresh()));
        $this->assertFalse(app(RecordingService::class)->retryFailed($recording->fresh(), $admin));

        $this->assertSame($before, $recording->fresh()->only(['status', 'failure_code', 'storage_driver', 'storage_path', 'storage_checksum', 'size_bytes', 'provider', 'capture_attempts', 'consent_snapshot']));
        $this->assertNotNull($this->storage->storedName($before['storage_path']), 'the preserved object is still there');
        Queue::assertNotPushed(CaptureLessonRecordingJob::class);
        $this->assertSame(0, Activity::query()->where('event', 'recording_retry_requested')->count(), 'a refused retry is not recorded as a retry');
    }

    /** Any Failed row that still points at a stored object is refused, whatever its failure code. */
    public function test_direct_retry_of_a_failed_row_with_a_locator_is_refused(): void
    {
        $recording = Recording::factory()->create([
            'provider' => FakeMeetingProvider::KEY,
            'status' => RecordingStatus::Failed,
            'failure_code' => 'storage_verification_failed',
            'storage_driver' => InMemoryRecordingStorage::KEY,
            'storage_path' => 'recordings/2026/09/lesson-preserved.mp4',
            'size_bytes' => 512,
            'capture_attempts' => 2,
        ]);
        Queue::fake();

        $this->assertFalse(app(RecordingService::class)->retryFailed($recording, $this->admin()));

        $fresh = $recording->fresh();
        $this->assertSame(RecordingStatus::Failed, $fresh->status);
        $this->assertSame('storage_verification_failed', $fresh->failure_code->value);
        $this->assertSame('recordings/2026/09/lesson-preserved.mp4', $fresh->storage_path);
        $this->assertSame(2, $fresh->capture_attempts);
        Queue::assertNotPushed(CaptureLessonRecordingJob::class);
    }

    /** A legitimately failed recording with nothing stored stays retryable. */
    public function test_a_failed_row_without_a_stored_object_is_still_retryable(): void
    {
        $recording = Recording::factory()->create([
            'provider' => FakeMeetingProvider::KEY,
            'status' => RecordingStatus::Failed,
            'failure_code' => 'capture_retries_exhausted',
            'storage_path' => null,
            'capture_attempts' => 5,
        ]);

        $this->assertNull(app(RecordingService::class)->retryRefusalReason($recording));
        $this->assertTrue(app(RecordingService::class)->retryFailed($recording, $this->admin()));

        $fresh = $recording->fresh();
        $this->assertSame(RecordingStatus::Pending, $fresh->status);
        $this->assertNull($fresh->failure_code);
        $this->assertSame(0, $fresh->capture_attempts);
        $this->assertDatabaseHas('activity_log', ['event' => 'recording_retry_requested', 'subject_id' => $recording->id]);
    }
}
