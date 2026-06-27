<?php

namespace App\Filament\Resources\PageBlocks\Pages;

use App\Actions\ValidateBlockContentAction;
use App\Enums\BlockType;
use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Models\Page;
use App\Services\BlockContentConverter;
use App\Services\BlockContentHydrator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPageBlock extends EditRecord
{
    protected static string $resource = PageBlockResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->record && $this->record->block_type) {
            $blockType = $this->record->block_type instanceof BlockType
                ? $this->record->block_type
                : BlockType::tryFrom($this->record->block_type);

            if ($blockType) {
                $hydratedData = BlockContentHydrator::hydrate($blockType, $this->record->content ?? []);

                $this->form->fill([
                    ...$this->record->toArray(),
                    // Expose blockable_id as page_id for the form Select field
                    'page_id' => $this->record->blockable_id,
                    ...$hydratedData,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Map virtual page_id → polymorphic pair (preserved across the edit form)
        if (isset($data['page_id'])) {
            $data['blockable_type'] = (new Page)->getMorphClass();
            $data['blockable_id']   = $data['page_id'];
            unset($data['page_id']);
        }

        if (isset($data['block_type'])) {
            $blockType = $data['block_type'] instanceof BlockType
                ? $data['block_type']
                : BlockType::tryFrom($data['block_type']);

            if ($blockType) {
                $data['content']  = BlockContentConverter::convert($blockType, $data);
                $data['settings'] = $data['settings'] ?? [];

                $errors = app(ValidateBlockContentAction::class)->execute($blockType, $data['content']);
                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        'block_type' => implode(' ', $errors),
                    ]);
                }
            }
        }

        $databaseColumns = [
            'id', 'blockable_type', 'blockable_id', 'block_type', 'content',
            'settings', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at',
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
