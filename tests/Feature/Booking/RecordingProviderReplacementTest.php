<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\DTOs\RecordingProviderReconciliation;
use App\Booking\Enums\BookingStatus;
use App\Booking\Enums\MeetingStatus;
use App\Booking\Enums\RecordingStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
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
}
