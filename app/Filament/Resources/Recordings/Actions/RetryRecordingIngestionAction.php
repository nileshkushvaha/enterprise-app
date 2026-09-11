<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recordings\Actions;

use App\Booking\Enums\RecordingStatus;
use App\Booking\Jobs\CaptureLessonRecordingJob;
use App\Booking\Services\RecordingService;
use App\Models\Recording;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The single administrative write path into a recording, and the only
 * manual recovery the SRS's "record the failure and notify
 * administrators" (§12.35) actually needs.
 *
 * Safety properties, all enforced below the UI rather than by hiding
 * the button:
 *
 *  - AUTHORIZED — RecordingService::retryFailed() calls
 *    RecordingPolicy::retry(), so a crafted request is refused even
 *    though the action is also hidden for unauthorized users.
 *  - AUDITED — every retry is written to the audit trail with the
 *    acting admin and the previous failure code.
 *  - IDEMPOTENT — only a Failed row transitions, under a row lock. A
 *    double-click, or a retry racing the reconciliation sweep, cannot
 *    start two ingestions or produce a second stored object.
 *  - NON-DESTRUCTIVE — it never deletes, and never re-uploads over an
 *    object that already exists. A Failed row that still holds a
 *    locator (publication refused because the meeting was replaced
 *    mid-capture) is disabled here and refused by the service; that
 *    object needs operator recovery, not another pass.
 */
final class RetryRecordingIngestionAction
{
    public static function make(): Action
    {
        return Action::make('retryIngestion')
            ->label('Retry ingestion')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Retry recording ingestion')
            ->modalDescription('The recording is fetched from the meeting provider and stored again, in the background. Recordings that are already stored are never touched. The current state is re-checked when you confirm.')
            ->visible(fn (Recording $record): bool => $record->status === RecordingStatus::Failed
                && auth()->user()?->can('retry', $record) === true)
            // A Failed row that still points at a preserved object (the
            // meeting was replaced mid-capture) is shown but not
            // retryable: the label and tooltip say that operator recovery
            // is required. The service refuses regardless, under its lock.
            ->label(fn (Recording $record, RecordingService $recordings): string => $recordings->retryRefusalReason($record) !== null
                ? 'Operator recovery required'
                : 'Retry ingestion')
            ->disabled(fn (Recording $record, RecordingService $recordings): bool => $recordings->retryRefusalReason($record) !== null)
            ->tooltip(fn (Recording $record, RecordingService $recordings): ?string => $recordings->retryRefusalReason($record))
            ->action(function (Recording $record, RecordingService $recordings): void {
                // The service takes the decision under its row lock on a
                // fresh read; a stale page (row no longer failed) or a
                // protected row therefore never queues anything. The
                // refreshed row is only used to phrase the outcome.
                $queued = $recordings->retryFailed($record, auth()->user());

                if (! $queued) {
                    $record->refresh();
                    $refusal = $recordings->retryRefusalReason($record);

                    Notification::make()
                        ->title($refusal !== null ? 'Operator recovery required' : 'Nothing to retry')
                        ->body($refusal ?? sprintf('This recording is now %s, so there is nothing to retry. The page has been refreshed.', $record->status->label()))
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                // Queued, never inline: an admin click must not hold an
                // HTTP request open for the length of a video transfer.
                CaptureLessonRecordingJob::dispatch($record->getKey());

                Notification::make()
                    ->title('Retry queued')
                    ->body('The recording will be fetched and stored in the background.')
                    ->success()
                    ->send();
            });
    }
}
