<?php

namespace App\Filament\Resources\PostBlocks\Schemas;

use App\Enums\BlockType;
use App\Forms\BlockFormSchemaFactory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PostBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('post_block_tabs')
                    ->tabs([
                        Tabs\Tab::make('Post')
                            ->schema([
                                Section::make('Post')
                                    ->schema([
                                        Select::make('post_id')
                                            ->label('Post')
                                            ->relationship('post', 'title')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Content')
                            ->schema([
                                Section::make('Block Type')
                                    ->schema([
                                        Select::make('block_type')
                                            ->label('Block Type')
                                            ->options(BlockType::class)
                                            ->required(),
                                    ]),
                                Section::make('Block Content')
                                    ->schema(fn (Get $get): array => self::getDynamicBlockFields($get('block_type')))
                                    ->visible(fn (Get $get): bool => filled(self::normalizeBlockType($get('block_type')))),
                            ]),
                        Tabs\Tab::make('Settings')
                            ->schema([
                                Section::make('Block Settings')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true),
                                    ]),
                            ]),
                        Tabs\Tab::make('Ordering')
                            ->schema([
                                Section::make('Display Order')
                                    ->schema([
                                        TextInput::make('sort_order')
                                            ->label('Sort Order')
                                            ->numeric()
                                            ->required()
                                            ->default(0),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function getDynamicBlockFields(mixed $blockType): array
    {
        $normalized = self::normalizeBlockType($blockType);

        if (! $normalized) {
            return [];
        }

        $blockTypeEnum = BlockType::tryFrom($normalized);

        if (! $blockTypeEnum) {
            return [];
        }

        return BlockFormSchemaFactory::make($blockTypeEnum);
    }

    private static function normalizeBlockType(mixed $value): ?string
    {
        if ($value instanceof BlockType) {
            return $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}

