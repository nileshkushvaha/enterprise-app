<?php

namespace App\Filament\Resources\PostBlocks\Pages;

use App\Actions\ValidateBlockContentAction;
use App\Enums\BlockType;
use App\Filament\Resources\PostBlocks\PostBlockResource;
use App\Services\BlockContentConverter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePostBlock extends CreateRecord
{
    protected static string $resource = PostBlockResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['post_id'] = $data['post_id'] ?? request()->query('post_id');

        if (blank($data['post_id'])) {
            throw ValidationException::withMessages([
                'post_id' => 'Please select a post before creating a block.',
            ]);
        }

        if (isset($data['block_type'])) {
            $blockTypeValue = $data['block_type'] instanceof BlockType ? $data['block_type']->value : $data['block_type'];
            $blockType = BlockType::tryFrom((string) $blockTypeValue);
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

        $databaseColumns = ['post_id', 'block_type', 'content', 'settings', 'sort_order', 'is_active', 'created_at', 'updated_at'];

        return array_intersect_key($data, array_flip($databaseColumns));
    }
}

