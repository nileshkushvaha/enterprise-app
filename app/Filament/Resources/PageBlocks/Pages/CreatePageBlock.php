<?php

namespace App\Filament\Resources\PageBlocks\Pages;

use App\Enums\BlockType;
use App\Actions\ValidateBlockContentAction;
use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Services\BlockContentConverter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePageBlock extends CreateRecord
{
    protected static string $resource = PageBlockResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Support create URLs like /admin/page-blocks/create?page_id=<uuid>
        $data['page_id'] = $data['page_id'] ?? request()->query('page_id');

        if (blank($data['page_id'])) {
            throw ValidationException::withMessages([
                'page_id' => 'Please select a page before creating a block.',
            ]);
        }

        // Convert form data to JSON
        if (isset($data['block_type'])) {
            $blockTypeValue = $data['block_type'] instanceof BlockType ? $data['block_type']->value : $data['block_type'];
            $blockType = BlockType::tryFrom($blockTypeValue);
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

        // Clean up form fields that aren't database columns
        $databaseColumns = [
            'page_id', 'block_type', 'content', 'settings', 'sort_order', 
            'is_active', 'created_at', 'updated_at'
        ];

        return array_intersect_key($data, array_flip($databaseColumns));
    }
}
