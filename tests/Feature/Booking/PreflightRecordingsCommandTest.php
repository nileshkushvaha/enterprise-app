<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\RecordingFailureCode;
use App\Models\Recording;
use App\Settings\MeetingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** recordings:preflight — read-only, presence is not access, nothing sensitive printed. */
final class PreflightRecordingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_missing_storage_configuration_as_a_problem_without_probing(): void
    {
        config(['recordings.storage_driver' => 'google_drive']);
        $settings = app(MeetingSettings::class);
        $settings->recording_enabled = true;
        $settings->recording_drive_root_folder_id = null;
        $settings->save();

        $exit = Artisan::call('recordings:preflight');
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('google_drive', $out);
        $this->assertStringContainsString('not probed', $out);
        $this->assertStringContainsString('not configured', $out);
        $this->assertStringContainsString('Nothing was changed', $out);
    }

    public function test_presence_of_configuration_is_never_reported_as_proven_access(): void
    {
        config(['recordings.storage_driver' => 'google_drive']);
        $settings = app(MeetingSettings::class);
        $settings->recording_enabled = false;
        $settings->recording_drive_root_folder_id = 'folder-id-present';
        $settings->platform_meeting_account = 'meetings@example.test';
        $settings->save();

        $this->artisan('recordings:preflight')
            ->expectsOutputToContain('present')
            ->expectsOutputToContain('not probed')
            ->doesntExpectOutputToContain('PROVEN')
            ->doesntExpectOutputToContain('folder-id-present');
    }

    public function test_it_counts_failed_and_preserved_recordings_and_changes_nothing(): void
    {
        $failed = Recording::factory()->failed()->create([
            'failure_code' => RecordingFailureCode::MeetingReplacedDuringCapture,
            'storage_driver' => 'filesystem',
            'storage_path' => 'recordings/2026/09/lesson-SECRETLOCATOR.mp4',
        ]);
        Recording::factory()->transferring()->create();
        $settings = app(MeetingSettings::class);
        $settings->recording_enabled = false;
        $settings->save();

        Artisan::call('recordings:preflight', ['--json' => true]);
        $json = Artisan::output();

        $this->assertStringContainsString('"meeting_replaced_during_capture": 1', $json);
        $this->assertStringContainsString('"failed_with_preserved_object": 1', $json);
        $this->assertStringContainsString('"transferring_past_stale_threshold": 1', $json);
        $this->assertStringNotContainsString('SECRETLOCATOR', $json);

        $this->assertSame('recordings/2026/09/lesson-SECRETLOCATOR.mp4', $failed->fresh()->storage_path);
    }

    public function test_a_join_domain_with_a_non_shared_cache_store_is_flagged(): void
    {
        config(['cache.default' => 'array']);
        $settings = app(MeetingSettings::class);
        $settings->participant_join_base_url = 'https://meet.example.test';
        $settings->recording_enabled = false;
        $settings->save();

        $this->artisan('recordings:preflight')
            ->expectsOutputToContain('not shared across web nodes')
            ->assertExitCode(1);
    }
}
