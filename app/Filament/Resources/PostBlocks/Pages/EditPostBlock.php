<?php

namespace App\Filament\Resources\PostBlocks\Pages;

use App\Actions\ValidateBlockContentAction;
use App\Enums\BlockType;
use App\Filament\Resources\PostBlocks\PostBlockResource;
use App\Services\BlockContentConverter;
use App\Services\BlockContentHydrator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPostBlock extends EditRecord
{
    protected static string $resource = PostBlockResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();

        if ($this->record && $this->record->block_type) {
            $blockType = $this->record->block_type instanceof BlockType
                ? $this->record->block_type
                : BlockType::tryFrom((string) $this->record->block_type);

            if ($blockType) {
                $hydratedData = BlockContentHydrator::hydrate($blockType, $this->record->content ?? []);
                $this->form->fill([
                    ...$this->record->toArray(),
                    ...$hydratedData,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['block_type'])) {
            $blockType = $data['block_type'] instanceof BlockType
                ? $data['block_type']
                : BlockType::tryFrom((string) $data['block_type']);

            if ($blockType) {
                $data['content'] = BlockContentConverter::convert($blockType, $data);
                $data['settings'] = $data['settings'] ?? [];

                $errors = app(ValidateBlockContentAction::class)->execute($blockType, $data['content']);
                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        'block_type' => implode(' ', $errors),
                    ]);
                }
            }
        }

        $databaseColumns = ['id', 'post_id', 'block_type', 'content', 'settings', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at'];

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

