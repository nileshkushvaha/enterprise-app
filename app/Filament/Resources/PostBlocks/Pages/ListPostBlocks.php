<?php

namespace App\Filament\Resources\PostBlocks\Pages;

use App\Filament\Resources\PostBlocks\PostBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPostBlocks extends ListRecords
{
    protected static string $resource = PostBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

