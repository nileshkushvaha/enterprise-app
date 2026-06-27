<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Services\PostService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['featured_image']);

        return $data;
    }

    protected function afterSave(): void
    {
        $featuredImage = $this->form->getRawState()['featured_image'] ?? null;

        if ($featuredImage) {
            $this->record->clearMediaCollection('featured-image');
            $this->record->addMedia($featuredImage)->toMediaCollection('featured-image');

            activity()
                ->performedOn($this->record)
                ->causedBy(auth()->user())
                ->event('media_updated')
                ->log('Post featured image updated');
        }

        app(PostService::class)->refreshReadingTime($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

