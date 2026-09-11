<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\Enums\BookingStatus;
use App\Booking\Jobs\CaptureLessonRecordingJob;
use App\Booking\Registry\MeetingProviderRegistry;
use App\Booking\Services\RecordingService;
use App\Booking\Services\RecordingStagingArea;
use App\Models\Recording;
use App\Settings\MeetingSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Eventual correctness for the recording pipeline. The queued
 * CaptureLessonRecordingJob provides low latency; this sweep provides
 * the guarantee — a recording is never lost because a worker restarted,
 * the queue was down, or the meeting provider was still processing the
 * file when the job ran.
 *
 * Three bounded jobs, in order:
 *
 *   1. reclaim rows abandoned mid-transfer by a crashed worker;
 *   2. queue a capture for every Pending/Stored recording inside the
 *      configured age and attempt window;
 *   3. purge staged temp files a crashed run left on disk.
 *
 * The sweep never transfers bytes itself. It dispatches the SAME
 * CaptureLessonRecordingJob the webhook and meeting creation use, onto
 * the dedicated `recordings` connection/queue, so a multi-gigabyte
 * download can never run inside the scheduler process (where it would
 * hold `schedule:run` — and every other scheduled command behind it —
 * for the length of the transfer, with none of the recordings worker's
 * timeout or retry_after protections). Every path into a transfer is
 * therefore one job class, one queue, one worker configuration.
 *
 * Duplicate safety is unchanged: the job's row-level claim makes a
 * sweep dispatch racing a webhook dispatch resolve to one transfer,
 * and the job's unique lock keeps one queued job per recording while a
 * worker is down. The attempt budget, retry window and eligibility
 * filters are applied here, before anything is queued.
 *
 * Bounded on every axis: a date window (never the whole table), an
 * attempt budget, and a batch size, with lazyById() paging. One
 * recording's failure never stops the batch — ingestion records its
 * own outcome on the row.
 */
final class CaptureLessonRecordings extends Command
{
    protected $signature = 'recordings:capture';

    protected $description = 'Reconcile pending lesson recordings against their meeting provider and storage backend';

    public function handle(
        RecordingService $recordings,
        MeetingProviderRegistry $registry,
        RecordingStagingArea $staging,
        MeetingSettings $settings,
    ): int {
        $batchSize = max(1, $settings->recording_capture_batch_size);

        $reclaimed = $recordings->reclaimStalledTransfers($settings->recording_transfer_stale_minutes, $batchSize);

        // Row reconciliation first, so a lesson registered late in this
        // run is also captured in this run when it is already due.
        $registered = $recordings->registerMissing($registry, $settings->recording_capture_retry_minutes, $batchSize);

        $endedBefore = now()->subMinutes(max(0, $settings->recording_capture_delay_minutes));
        $endedAfter = now()->subHours(max(1, $settings->recording_capture_max_age_hours));

        $due = Recording::query()
            ->dueForCapture()
            ->where('capture_attempts', '<', max(1, $settings->recording_capture_max_attempts))
            ->whereHas('bookingMeeting', fn ($query) => $query->whereBetween('ends_at', [$endedAfter, $endedBefore]))
            // A lesson that was cancelled after its meeting (and recording
            // row) were created produced nothing: never look for it, and
            // never raise a "no recording" alert for it.
            ->whereHas('booking', fn ($query) => $query->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed]))
            ->with('bookingMeeting')
            ->lazyById($batchSize);

        $queued = 0;

        foreach ($due as $recording) {
            if (! $registry->has($recording->provider)) {
                continue;
            }

            try {
                // Queued, never captured inline — see the class docblock.
                // A dispatch the unique lock suppresses (a job for this
                // recording is already waiting) is still counted: the
                // recording IS queued, just not twice.
                CaptureLessonRecordingJob::dispatch($recording->getKey());
                $queued++;
            } catch (Throwable $e) {
                $this->error(sprintf('Recording %s: %s', $recording->getKey(), $e->getMessage()));
            }
        }

        $purged = $staging->purgeStale();

        $this->info(sprintf(
            'Registered %d missing recording(s); queued %d pending recording(s); reclaimed %d stalled transfer(s); purged %d stale staged file(s).',
            $registered,
            $queued,
            $reclaimed,
            $purged,
        ));

        return self::SUCCESS;
    }
}
