<?php

namespace App\Filament\Resources\PageBlocks\Schemas;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use App\Enums\BlockType;
use App\Forms\BlockFormSchemaFactory;
use Filament\Schemas\Schema;

class PageBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('page_block_tabs')
                    ->tabs([
                        Tabs\Tab::make('Owner')
                            ->schema([
                                Section::make('Content Owner')
                                    ->description('Choose whether this block belongs to a Page or a Post.')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('blockable_type')
                                            ->label('Type')
                                            ->options([
                                                'page' => 'Page',
                                                'post' => 'Post',
                                            ])
                                            ->default('page')
                                            ->live()
                                            ->required(),

                                        Select::make('page_id')
                                            ->label('Page')
                                            ->relationship('page', 'title')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->visible(fn (Get $get): bool => $get('blockable_type') === 'page'),

                                        Select::make('post_id')
                                            ->label('Post')
                                            ->relationship('post', 'title')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->visible(fn (Get $get): bool => $get('blockable_type') === 'post'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Content')
                            ->schema([
                                Section::make('Block Identity')
                                    ->description('Give this block a name so you can identify it at a glance in the block list.')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Block Name')
                                            ->placeholder('e.g. Homepage Hero, About Intro, CTA Banner')
                                            ->helperText('Optional — shown in the block list instead of the block type label.')
                                            ->maxLength(100)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Block Type')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('block_type')
                                            ->label('Block Type')
                                            ->options(BlockType::class)
                                            ->required()
                                            ->live()
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Block Content')
                                    ->collapsible(false)
                                    ->schema(fn (Get $get): array => self::getDynamicBlockFields($get('block_type')))
                                    ->visible(fn (Get $get): bool => filled(self::normalizeBlockType($get('block_type')))),
                            ]),

                        Tabs\Tab::make('Settings')
                            ->schema([
                                Section::make('Block Settings')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('position')
                                            ->label('Position')
                                            ->options([
                                                'before_content' => 'Before Main Content',
                                                'after_content'  => 'After Main Content',
                                            ])
                                            ->default('after_content')
                                            ->native(false)
                                            ->helperText('Where this block renders relative to the primary rich content.'),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Enable or disable this block'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Ordering')
                            ->schema([
                                Section::make('Display Order')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInput::make('sort_order')
                                            ->label('Sort Order')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->helperText('Lower numbers appear first'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
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
