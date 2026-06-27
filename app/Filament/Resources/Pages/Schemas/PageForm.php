<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

use App\Actions\GeneratePageSlugAction;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('page_tabs')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Section::make('Page Information')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInput::make('title')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (blank($state)) {
                                                    return;
                                                }
                                                $set('slug', app(GeneratePageSlugAction::class)->execute($state));
                                            }),
                                        TextInput::make('slug')
                                            ->required()
                                            ->unique('pages', 'slug', ignoreRecord: true)
                                            ->maxLength(255)
                                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                            ->helperText('URL-friendly identifier. Auto-generated from title.'),
                                        Textarea::make('excerpt')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->helperText('Brief summary of the page content.'),
                                    ]),

                                Section::make('Media')
                                    ->collapsible(false)
                                    ->schema([
                                        FileUpload::make('featured_image')
                                            ->image()
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                            ->maxSize(5120)
                                            ->helperText('Upload a featured image (max 5MB)')
                                    ]),

                                Section::make('Template & Layout')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('template')
                                            ->options([
                                                'default' => 'Default',
                                                'landing' => 'Landing Page',
                                                'blank' => 'Blank',
                                            ])
                                            ->default('default'),
                                        Select::make('layout')
                                            ->options([
                                                'default' => 'Default',
                                                'sidebar-left' => 'Sidebar Left',
                                                'sidebar-right' => 'Sidebar Right',
                                                'full-width' => 'Full Width',
                                            ])
                                            ->default('default'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Publishing')
                            ->schema([
                                Section::make('Publication Settings')
                                    ->collapsible(false)
                                    ->schema([
                                        Select::make('status')
                                            ->options(PageStatus::class)
                                            ->default(PageStatus::Draft)
                                            ->native(false)
                                            ->required(),
                                        Select::make('visibility')
                                            ->options(PageVisibility::class)
                                            ->default(PageVisibility::Private)
                                            ->native(false)
                                            ->required(),
                                        DateTimePicker::make('published_at')
                                            ->nullable()
                                            ->helperText('When should this page be published?'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Blocks')
                            ->schema([
                                Section::make('Page Blocks')
                                    ->collapsible(false)
                                    ->description('Blocks are managed from the dedicated Blocks resource to keep schema contracts consistent.')
                                    ->schema([
                                        Placeholder::make('blocks_notice')
                                            ->label('Block Management')
                                            ->content('Use Admin > Blocks to create and edit typed blocks. This keeps Form → Converter → Hydrator → Renderer contracts synchronized.'),
                                    ]),
                            ]),
                        
                        Tabs\Tab::make('SEO')
                            ->schema([
                                Section::make('Search Engine Optimization')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->maxLength(70)
                                            ->helperText('Recommended: 50-70 characters'),
                                        Textarea::make('meta_description')
                                            ->maxLength(160)
                                            ->rows(3)
                                            ->helperText('Recommended: 150-160 characters'),
                                        Textarea::make('meta_keywords')
                                            ->maxLength(255)
                                            ->rows(2)
                                            ->helperText('Comma-separated keywords'),
                                        TextInput::make('canonical_url')
                                            ->url()
                                            ->nullable(),
                                        Select::make('robots')
                                            ->options([
                                                'index, follow' => 'Index & Follow',
                                                'noindex, follow' => 'No Index, Follow',
                                                'index, nofollow' => 'Index, No Follow',
                                                'noindex, nofollow' => 'No Index, No Follow',
                                            ])
                                            ->default('index, follow')
                                            ->native(false),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
