<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthor extends CreateRecord
{
    protected static string $resource = AuthorResource::class;

    protected function afterCreate(): void
    {
        if (method_exists($this->record, 'hasRole') && ! $this->record->hasRole('author') && \Spatie\Permission\Models\Role::where('name', 'author')->exists()) {
            $this->record->assignRole('author');
        }
    }
}
