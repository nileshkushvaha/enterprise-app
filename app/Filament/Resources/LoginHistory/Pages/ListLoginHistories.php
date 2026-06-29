<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginHistory\Pages;

use App\Filament\Resources\LoginHistory\LoginHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginHistories extends ListRecords
{
    protected static string $resource = LoginHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
