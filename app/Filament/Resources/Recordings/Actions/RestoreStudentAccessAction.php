<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recordings\Actions;

use App\Booking\Services\RecordingService;
use App\Models\Recording;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** The exact inverse of WithholdStudentAccessAction; same guarantees. */
final class RestoreStudentAccessAction
{
    public static function make(): Action
    {
        return Action::make('restoreStudentAccess')
            ->label('Restore student access')
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore student access')
            ->modalDescription('Returns this recording to the normal student playback policy. It does not by itself make the recording playable: the recording must be available, student playback must be enabled, and the retention window must be open.')
            ->visible(fn (Recording $record): bool => $record->isStudentAccessWithheld()
                && auth()->user()?->can('withhold', $record) === true)
            ->action(function (Recording $record, RecordingService $recordings): void {
                $changed = $recordings->restoreStudentAccess($record, auth()->user());

                Notification::make()
                    ->title($changed ? 'Student access restored' : 'Nothing to restore')
                    ->body($changed
                        ? 'The student can watch this recording again.'
                        : 'This recording was not withheld.')
                    ->{$changed ? 'success' : 'warning'}()
                    ->send();
            });
    }
}
