<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\RecordingFailureCode;
use App\Models\BookingMeeting;
use App\Models\Recording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** recordings:inspect — read-only, evidence only, no locator or URL. */
final class InspectRecordingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_the_failure_evidence_and_changes_nothing(): void
    {
        $recording = Recording::factory()->failed()->create([
            'provider' => 'zoom',
            'failure_code' => RecordingFailureCode::SourceNotFound,
            'capture_attempts' => 4,
            'storage_path' => null,
        ]);
        $before = $recording->fresh()->toArray();

        $this->artisan('recordings:inspect '.$recording->booking->reference)
            ->expectsOutputToContain('source_not_found')
            ->expectsOutputToContain('No recording was produced for this lesson')
            ->expectsOutputToContain('zoom / zoom')
            ->expectsOutputToContain('allowed (admin action "Retry ingestion")')
            ->expectsOutputToContain('Nothing was changed')
            ->assertExitCode(0);

        $this->assertSame($before, $recording->fresh()->toArray());
    }

    public function test_it_flags_a_provider_mismatch_and_a_preserved_locator_without_printing_it(): void
    {
        $recording = Recording::factory()->failed()->create([
            'provider' => 'zoom',
            'failure_code' => RecordingFailureCode::MeetingReplacedDuringCapture,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-SECRETLOCATOR.mp4',
        ]);
        // The booking's live meeting moved to another provider.
        $recording->bookingMeeting->forceFill(['provider' => 'google_meet'])->save();

        $this->artisan('recordings:inspect '.$recording->booking_id)
            ->expectsOutputToContain('MISMATCH')
            ->expectsOutputToContain('filesystem / present')
            ->expectsOutputToContain('refused')
            ->doesntExpectOutputToContain('SECRETLOCATOR')
            ->assertExitCode(0);

        $this->assertNotNull(BookingMeeting::query()->find($recording->booking_meeting_id));
    }

    public function test_an_unknown_booking_fails_cleanly(): void
    {
        $this->artisan('recordings:inspect BK-NOPE')->assertExitCode(1);
    }
}
