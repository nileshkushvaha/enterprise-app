<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recordings\Actions;

use App\Models\Recording;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Administrative download of the original file. A plain link to the
 * application-proxied download route — RecordingPolicy::download() and
 * the playable-state check are re-run there on every request, so this
 * action only decides whether to SHOW the link, from the row and the
 * policy alone (available status + locator present + permission). It
 * never calls a provider or storage API, and never builds, nor could
 * build, a storage or provider URL.
 */
final class DownloadRecordingAction
{
    public static function make(): Action
    {
        return Action::make('downloadRecording')
            ->label('Download')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->tooltip('Downloads the original file through SIRI. Verified on request.')
            // The policy already requires Available + locator for
            // everyone; super admins bypass policies (Gate::before), so
            // the same state rule is applied here explicitly too.
            ->visible(fn (Recording $record): bool => $record->isPlayable()
                && auth()->user()?->can('download', $record) === true)
            ->url(fn (Recording $record): string => route('admin.recordings.download', $record))
            ->openUrlInNewTab();
    }
}
