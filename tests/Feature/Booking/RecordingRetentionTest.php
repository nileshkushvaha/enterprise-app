<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\RecordingStatus;
use App\Booking\Exceptions\RecordingStorageException;
use App\Booking\Meetings\FakeMeetingProvider;
use App\Booking\Services\RecordingService;
use App\Booking\Services\RecordingStagingArea;
use App\Models\Recording;
use App\Settings\MeetingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\Support\InMemoryRecordingStorage;
use Tests\TestCase;

/**
 * Retention (SRS §12.21), stated precisely:
 *
 *   a recording's stored copy is deleted `recording_retention_days`
 *   after the class was RECORDED; the metadata row survives.
 *
 * "After the class was recorded" is the load-bearing part. Anchoring
 * on the publish instant would let a recording that took a day to
 * arrive live a day longer than one that arrived at once — and would
 * make the promise depend on queue latency. The number of days is the
 * admin setting, never a literal, and the deletion boundary is exact.
 */
final class RecordingRetentionTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryRecordingStorage $storage;

    private RecordingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        FakeMeetingProvider::reset();

        $this->storage = new InMemoryRecordingStorage;
        $this->app->instance(InMemoryRecordingStorage::class, $this->storage);
        config([
            'recordings.storage_driver' => InMemoryRecordingStorage::KEY,
            'recordings.drivers' => [InMemoryRecordingStorage::KEY => InMemoryRecordingStorage::class],
        ]);

        $this->service = app(RecordingService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        File::deleteDirectory(app(RecordingStagingArea::class)->path());

        parent::tearDown();
    }

    private function fakeMp4Bytes(): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 200);
    }

    private function retention(int $days): void
    {
        $settings = app(MeetingSettings::class);
        $settings->recording_retention_days = $days;
        $settings->save();
    }

    /** Captures one recording at a frozen instant; the fake provider reports it was recorded five minutes earlier. */
    private function capturedRecording(CarbonImmutable $publishedAt): Recording
    {
        CarbonImmutable::setTestNow($publishedAt);

        $recording = Recording::factory()->create(['provider' => FakeMeetingProvider::KEY]);
        $recording->bookingMeeting->update(['ends_at' => $publishedAt->subHour(), 'provider' => FakeMeetingProvider::KEY]);
        FakeMeetingProvider::$nextRecordingContents = $this->fakeMp4Bytes();

        $this->service->capture($recording->fresh(), new FakeMeetingProvider);

        return $recording->fresh();
    }

    // ── The rule ──────────────────────────────────────────────────────

    public function test_expiry_is_exactly_the_configured_days_after_the_recording_time_not_after_publication(): void
    {
        $this->retention(30);
        $publishedAt = CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC');

        $recording = $this->capturedRecording($publishedAt);

        $this->assertSame(RecordingStatus::Available, $recording->status);
        $this->assertTrue($recording->recorded_at->lessThan($recording->available_at), 'the fixture records before it publishes');
        $this->assertTrue(
            $recording->expires_at->equalTo($recording->recorded_at->addDays(30)),
            sprintf('expected %s, got %s', $recording->recorded_at->addDays(30), $recording->expires_at),
        );
        $this->assertFalse($recording->expires_at->equalTo($recording->available_at->addDays(30)), 'anchored on recorded_at, not on available_at');
    }

    /** The default shipped in settings is thirty days — and the code carries no literal of its own. */
    public function test_the_shipped_default_retention_is_thirty_days(): void
    {
        $this->assertSame(30, app(MeetingSettings::class)->recording_retention_days);

        $recording = $this->capturedRecording(CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC'));

        $this->assertTrue($recording->expires_at->equalTo($recording->recorded_at->addDays(30)));
    }

    public function test_retention_is_configurable_through_the_meeting_settings(): void
    {
        $this->retention(7);

        $recording = $this->capturedRecording(CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC'));

        $this->assertTrue($recording->expires_at->equalTo($recording->recorded_at->addDays(7)));
    }

    /** A recording captured late still expires N days after the LESSON — the promise is about the class, not the transfer. */
    public function test_a_late_capture_does_not_extend_retention(): void
    {
        $this->retention(30);

        // The provider reports the recording time; SIRI's transfer finished much later.
        $recording = Recording::factory()->make([
            'recorded_at' => CarbonImmutable::parse('2026-09-01 10:00:00', 'UTC'),
            'available_at' => CarbonImmutable::parse('2026-09-05 10:00:00', 'UTC'),
        ]);

        $this->assertTrue($recording->retentionExpiryFor(30)->equalTo(CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC')));
    }

    // ── The fallback ──────────────────────────────────────────────────

    /**
     * Without a provider-supplied recording time the anchor falls back
     * to the publish instant, then to now. Both are LATER than the
     * true recording time, so the fallback only ever keeps a recording
     * longer — it can never delete one early.
     */
    public function test_without_a_recording_time_the_anchor_falls_back_to_publication_then_now(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-11 12:00:00', 'UTC'));

        $published = Recording::factory()->make([
            'recorded_at' => null,
            'available_at' => CarbonImmutable::parse('2026-09-10 09:00:00', 'UTC'),
        ]);
        $this->assertTrue($published->retentionExpiryFor(30)->equalTo(CarbonImmutable::parse('2026-10-10 09:00:00', 'UTC')));

        $unpublished = Recording::factory()->make(['recorded_at' => null, 'available_at' => null]);
        $this->assertTrue($unpublished->retentionExpiryFor(30)->equalTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC')));
    }

    /** A zero or negative setting can never produce an already-expired recording at publish time. */
    public function test_retention_is_never_shorter_than_one_day(): void
    {
        $recording = Recording::factory()->make(['recorded_at' => CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC')]);

        $this->assertTrue($recording->retentionExpiryFor(0)->equalTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC')));
    }

    // ── The boundary ──────────────────────────────────────────────────

    /**
     * One second before expiry the recording is still served; at the
     * exact instant it is due. `dueForExpiry` is `expires_at <= now`.
     */
    public function test_the_expiry_sweep_honours_the_exact_boundary(): void
    {
        $this->retention(30);
        $recording = $this->capturedRecording(CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC'));
        $expiresAt = $recording->expires_at;

        CarbonImmutable::setTestNow($expiresAt->subSecond());
        $this->artisan('recordings:expire')->expectsOutputToContain('Expired 0 recording(s)');
        $this->assertSame(RecordingStatus::Available, $recording->fresh()->status);
        $this->assertCount(0, $this->storage->deleted, 'nothing is deleted before the boundary');

        CarbonImmutable::setTestNow($expiresAt);
        $this->artisan('recordings:expire')->expectsOutputToContain('Expired 1 recording(s)');
        $this->assertSame(RecordingStatus::Expired, $recording->fresh()->status);
    }

    // ── What expiry deletes, and what it keeps ────────────────────────

    /**
     * Expiry removes exactly one thing: SIRI's own stored copy, through
     * the backend that holds it. The metadata row stays (§12.21) and
     * nothing at the meeting provider is touched — the Zoom cloud
     * original and the Meet-generated Drive file have their own,
     * separately configured retention.
     */
    public function test_expiry_deletes_only_the_siri_stored_copy_and_preserves_the_metadata(): void
    {
        $this->retention(30);
        $recording = $this->capturedRecording(CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC'));
        $locatorPath = $recording->storage_path;
        $this->assertNotNull($locatorPath);

        CarbonImmutable::setTestNow($recording->expires_at);
        $this->artisan('recordings:expire')->assertSuccessful();
        $expired = $recording->fresh();

        // The object: gone, through the recording's own storage driver.
        $this->assertSame([$locatorPath], $this->storage->deleted, 'exactly one object deleted: SIRI\'s copy');
        $this->assertCount(0, $this->storage->objects);

        // The row: kept, with everything except the locator.
        $this->assertSame(RecordingStatus::Expired, $expired->status);
        $this->assertNull($expired->storage_path, 'the locator points at nothing now');
        $this->assertSame(InMemoryRecordingStorage::KEY, $expired->storage_driver, 'where it used to live is evidence');
        $this->assertSame($recording->size_bytes, $expired->size_bytes);
        $this->assertSame($recording->duration_seconds, $expired->duration_seconds);
        $this->assertSame($recording->mime_type, $expired->mime_type);
        $this->assertTrue($expired->recorded_at->equalTo($recording->recorded_at));
        $this->assertTrue($expired->expires_at->equalTo($recording->expires_at));
        $this->assertSame($recording->provider_reference, $expired->provider_reference, 'the provider reference survives, the provider copy is never touched');
        $this->assertSame($recording->booking_id, $expired->booking_id);

        // Idempotent: a second sweep finds nothing to do.
        $this->artisan('recordings:expire')->expectsOutputToContain('Expired 0 recording(s)');
        $this->assertCount(1, $this->storage->deleted);
    }

    /** A failed storage deletion leaves the row Available for the next sweep — the row never claims a deletion that did not happen. */
    public function test_a_failed_deletion_leaves_the_recording_available_for_the_next_sweep(): void
    {
        $this->retention(30);
        $recording = $this->capturedRecording(CarbonImmutable::parse('2026-09-11 10:00:00', 'UTC'));
        $this->storage->failDelete = RecordingStorageException::uploadFailed('backend unavailable');

        CarbonImmutable::setTestNow($recording->expires_at);
        $this->artisan('recordings:expire')->expectsOutputToContain('Expired 0 recording(s)');

        $this->assertSame(RecordingStatus::Available, $recording->fresh()->status);
        $this->assertNotNull($recording->fresh()->storage_path);
    }
}
