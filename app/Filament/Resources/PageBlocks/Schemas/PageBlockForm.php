<?php

namespace App\Filament\Resources\PageBlocks\Schemas;

use Filament\Forms\Components\Hidden;
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
                        Tabs\Tab::make('Page')
                            ->schema([
                                Section::make('Page')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('page_id')
                                            ->label('Page')
                                            ->relationship('page', 'title')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Content')
                            ->schema([
                                Section::make('Block Type')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('block_type')
                                            ->label('Block Type')
                                            ->options(BlockType::class)
                                            
                                            ->required()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Settings')
                            ->schema([
                                Section::make('Block Settings')
                                    ->collapsible(false)
                                    ->schema([
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

                Hidden::make('content'),
                Hidden::make('settings'),
            ]);
    }
}
