<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\PostService;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['featured_image']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $featuredImage = $this->form->getRawState()['featured_image'] ?? null;

        if ($featuredImage) {
            $this->record->addMedia($featuredImage)->toMediaCollection('featured-image');

            activity()
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->event('media_updated')
                ->log('Post featured image uploaded');
        }

        app(PostService::class)->refreshReadingTime($this->record);
    }
}

