<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginHistory\Pages;

use App\Filament\Resources\LoginHistory\LoginHistoryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewLoginHistory extends ViewRecord
{
    protected static string $resource = LoginHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Login History')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(LoginHistoryResource::getUrl('index')),
        ];
    }
}
