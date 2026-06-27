<?php

namespace App\Filament\Resources\PageBlocks\Pages;

use App\Enums\BlockType;
use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Services\BlockContentConverter;
use App\Services\BlockContentHydrator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPageBlock extends EditRecord
{
    protected static string $resource = PageBlockResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();

        // Convert stored JSON back to form data
        if ($this->record && $this->record->block_type) {
            $blockType = $this->record->block_type instanceof BlockType
                ? $this->record->block_type
                : BlockType::tryFrom($this->record->block_type);
            if ($blockType) {
                $hydratedData = BlockContentHydrator::hydrate($blockType, $this->record->content ?? []);
                
                // Populate form with hydrated data
                $this->form->fill([
                    ...$this->record->toArray(),
                    ...$hydratedData,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert form data back to JSON
        if (isset($data['block_type'])) {
            $blockType = $data['block_type'] instanceof BlockType
                ? $data['block_type']
                : BlockType::tryFrom($data['block_type']);
            if ($blockType) {
                $data['content'] = BlockContentConverter::convert($blockType, $data);
                $data['settings'] = $data['settings'] ?? [];
            }
        }

        // Clean up form fields that aren't database columns
        $databaseColumns = [
            'id', 'page_id', 'block_type', 'content', 'settings', 'sort_order', 
            'is_active', 'created_at', 'updated_at', 'deleted_at'
        ];

        return array_intersect_key($data, array_flip($databaseColumns));
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
