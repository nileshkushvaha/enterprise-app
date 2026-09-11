<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recordings\Support;

use App\Booking\Enums\RecordingStatus;
use App\Booking\Services\RecordingService;
use App\Models\Recording;
use App\Settings\MeetingSettings;

/**
 * Turns a recording row into operator-readable sentences for the admin
 * list and Details page. Presentation only: every decision it phrases
 * (retry eligibility, playability, access) is taken by RecordingService,
 * RecordingPolicy or the model — this class never decides and never
 * writes. Nothing here prints a locator, token, URL or exception text.
 */
final class RecordingStatePresenter
{
    public function __construct(
        private readonly RecordingService $recordings,
        private readonly MeetingSettings $settings,
    ) {}

    /** What the status means right now — including that "pending" never means "never ran". */
    public function stateSummary(Recording $recording): string
    {
        $attempts = (int) $recording->capture_attempts;

        return match ($recording->status) {
            RecordingStatus::Pending => $attempts === 0
                ? 'Queued for capture. The capture job runs once the provider has published the recording; the reconciliation sweep re-queues it inside the capture window.'
                : sprintf('Waiting to retry after %d attempt%s. The reconciliation sweep re-queues it inside the capture window.', $attempts, $attempts === 1 ? '' : 's'),
            RecordingStatus::Transferring => sprintf(
                'Transfer in progress%s. A transfer abandoned by a crashed worker is reclaimed after %d minutes.',
                $recording->transfer_started_at !== null ? ' since '.$recording->transfer_started_at->diffForHumans() : '',
                $this->settings->recording_transfer_stale_minutes,
            ),
            RecordingStatus::Stored => 'Stored, awaiting verification. The object is uploaded; the next pass checks its size and checksum before the recording becomes available.',
            RecordingStatus::Available => $recording->isStudentAccessWithheld()
                ? 'Available and verified. Withheld from the student; administrators can still watch and download it.'
                : 'Available and verified. Student playback follows the platform policy.',
            RecordingStatus::Failed => sprintf(
                'Failed after %d attempt%s%s.%s',
                $attempts,
                $attempts === 1 ? '' : 's',
                $recording->failure_code !== null ? ': '.$recording->failure_code->label() : '',
                $recording->failure_code?->isPermanent() ? ' This failure is permanent for the automatic sweep.' : '',
            ),
            RecordingStatus::Expired => 'Expired. Retention ended, the stored object was removed, and only this record remains as evidence.',
        };
    }

    /** Concise failure for a table cell, or null when the row has not failed. */
    public function failureLabel(Recording $recording): ?string
    {
        if ($recording->status !== RecordingStatus::Failed) {
            return null;
        }

        return $recording->failure_code?->shortLabel() ?? 'Failed';
    }

    /** "3 of 5 attempts" — the budget is the configured capture maximum. */
    public function attemptsLabel(Recording $recording): string
    {
        return sprintf('%d of %d attempts', (int) $recording->capture_attempts, max(1, $this->settings->recording_capture_max_attempts));
    }

    /** The appropriate next step for an operator, phrased from the service's decision. */
    public function nextStep(Recording $recording): string
    {
        return match ($recording->status) {
            RecordingStatus::Failed => $this->failedNextStep($recording),
            RecordingStatus::Pending, RecordingStatus::Stored => sprintf(
                'No action needed. If this persists well past %d minutes, check that the recordings queue worker is running.',
                $this->settings->recording_capture_retry_minutes,
            ),
            RecordingStatus::Transferring => 'No action needed while the transfer runs. A stale transfer is reclaimed automatically.',
            RecordingStatus::Available => 'Nothing to do. Use Student access to withhold or restore the student\'s playback.',
            RecordingStatus::Expired => 'Nothing to do. Expired recordings are never restored.',
        };
    }

    /** True when the ordinary Retry ingestion action is refused for this row. */
    public function needsOperatorRecovery(Recording $recording): bool
    {
        return $this->recordings->retryRefusalReason($recording) !== null;
    }

    private function failedNextStep(Recording $recording): string
    {
        $refusal = $this->recordings->retryRefusalReason($recording);

        if ($refusal !== null) {
            return 'Operator recovery required. '.$refusal;
        }

        $guidance = $recording->failure_code?->operatorGuidance() ?? 'Fix the cause, then use Retry ingestion.';

        return $guidance.' Retry ingestion is available for this recording.';
    }
}
