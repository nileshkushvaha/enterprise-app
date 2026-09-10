<?php

declare(strict_types=1);

namespace App\Booking\Jobs;

use App\Booking\Registry\MeetingProviderRegistry;
use App\Booking\Services\RecordingService;
use App\Models\Recording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The ONLY place a class recording is transferred. Nothing about the
 * download or upload ever happens in an HTTP request, a Livewire
 * round-trip, or a controller — a webhook or a meeting creation does
 * the minimal trusted work (identify the lesson, persist the
 * discovery) and dispatches this afterCommit.
 *
 * Queue: a dedicated `recordings` connection and queue, because an
 * upload can legitimately run for many minutes and must never sit in
 * front of time-sensitive notification or payment work. That
 * connection's retry_after is configured ABOVE this job's timeout
 * (config/queue.php) — otherwise the queue would hand the same
 * recording to a second worker while the first is still uploading it.
 *
 * tries = 1 on purpose. Retry is the domain's job, not the queue's:
 * RecordingIngestionService records a retryable state on the row and
 * the bounded recordings:capture sweep picks it up. Queue-level
 * retries would multiply concurrent uploads of the same video and
 * bypass the attempt budget and retry window entirely.
 *
 * Idempotent regardless: capture() re-checks and atomically claims the
 * row, so a duplicate dispatch, a redelivered message, or the sweep
 * arriving at the same moment all resolve to one transfer.
 *
 * ShouldBeUnique is queue HYGIENE on top of that, not the guarantee.
 * The reconciliation sweep dispatches this job every fifteen minutes
 * for every due recording; while a worker is down that would stack
 * dozens of identical jobs per recording, each of which would run,
 * find the row already claimed or settled, and exit. The unique lock
 * (keyed on the recording id, held for at most uniqueFor seconds and
 * released when the job finishes or fails) means one queued job per
 * recording at a time. The row-level claim remains what makes a
 * concurrent run safe — if the lock is ever lost, nothing breaks.
 */
final class CaptureLessonRecordingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Long enough for a full-length lesson recording on a slow link. */
    public int $timeout = 3600;

    /** See the class docblock — retries belong to the domain, not the queue. */
    public int $tries = 1;

    /**
     * How long a stale unique lock can outlive a job that never
     * released it (a worker killed -9). Matches the job timeout, and is
     * shorter than the sweep's stalled-transfer reclaim, so a recording
     * is never blocked from re-dispatch for longer than it would be
     * blocked by its own Transferring claim.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $recordingId,
    ) {
        $this->onConnection('recordings');
        $this->onQueue('recordings');
    }

    public function uniqueId(): string
    {
        return $this->recordingId;
    }

    public function handle(RecordingService $recordings, MeetingProviderRegistry $registry): void
    {
        $recording = Recording::query()->find($this->recordingId);

        if ($recording === null) {
            return;
        }

        if (! $registry->has($recording->provider)) {
            return;
        }

        $recordings->capture($recording, $registry->get($recording->provider));
    }
}
