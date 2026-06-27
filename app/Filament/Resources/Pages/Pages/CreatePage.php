<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['featured_image']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $featuredImage = $this->form->getRawState()['featured_image'] ?? null;

        if ($featuredImage) {
            $this->record->addMedia($featuredImage)
                ->toMediaCollection('featured-image');

            activity()
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->event('media_updated')
                ->log('Page featured image uploaded');
        }
    }
}
