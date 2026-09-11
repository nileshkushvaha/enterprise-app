<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recordings\Pages;

use App\Filament\Resources\Recordings\Actions\DownloadRecordingAction;
use App\Filament\Resources\Recordings\Actions\RecordingActionGroup;
use App\Filament\Resources\Recordings\RecordingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRecording extends ViewRecord
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        // Download stays a visible button when it applies; everything
        // else lives in the same overflow menu as on the list.
        return [
            DownloadRecordingAction::make()->button(),
            RecordingActionGroup::make()->button(),
        ];
    }
}
