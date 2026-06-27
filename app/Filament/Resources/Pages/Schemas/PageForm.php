<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Actions\GeneratePageSlugAction;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use Filament\Forms\Components\DateTimePickerField;
use Filament\Forms\Components\FileUploadField;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\RichEditorField;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SelectField;
use Filament\Forms\Components\TabsField;
use Filament\Forms\Components\TextAreaField;
use Filament\Forms\Components\TextInputField;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TabsField::make('page_tabs')
                    ->tabs([
                        TabsField\Tab::make('General')
                            ->schema([
                                Section::make('Page Information')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInputField::make('title')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (blank($state)) {
                                                    return;
                                                }
                                                $set('slug', app(GeneratePageSlugAction::class)->execute($state));
                                            }),
                                        TextInputField::make('slug')
                                            ->required()
                                            ->unique('pages', 'slug', ignoreRecord: true)
                                            ->maxLength(255)
                                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                                            ->helperText('URL-friendly identifier. Auto-generated from title.'),
                                        TextAreaField::make('excerpt')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->helperText('Brief summary of the page content.'),
                                    ]),

                                Section::make('Media')
                                    ->collapsible(false)
                                    ->schema([
                                        FileUploadField::make('featured_image')
                                            ->image()
                                            ->imageEditor()
                                            ->imagePreviewHeight('250')
                                            ->maxSize(5120)
                                            ->helperText('Upload a featured image (max 5MB)')
                                            ->loadingIndicatorPosition('right')
                                            ->uploadProgressIndicatorPosition('right'),
                                    ]),

                                Section::make('Template & Layout')
                                    ->collapsible(false)
                                    ->schema([
                                        SelectField::make('template')
                                            ->options([
                                                'default' => 'Default',
                                                'landing' => 'Landing Page',
                                                'blank' => 'Blank',
                                            ])
                                            ->default('default'),
                                        SelectField::make('layout')
                                            ->options([
                                                'default' => 'Default',
                                                'sidebar-left' => 'Sidebar Left',
                                                'sidebar-right' => 'Sidebar Right',
                                                'full-width' => 'Full Width',
                                            ])
                                            ->default('default'),
                                    ]),
                            ]),

                        TabsField\Tab::make('Publishing')
                            ->schema([
                                Section::make('Publication Settings')
                                    ->collapsible(false)
                                    ->schema([
                                        SelectField::make('status')
                                            ->options(PageStatus::class)
                                            ->default(PageStatus::Draft)
                                            ->native(false)
                                            ->required(),
                                        SelectField::make('visibility')
                                            ->options(PageVisibility::class)
                                            ->default(PageVisibility::Private)
                                            ->native(false)
                                            ->required(),
                                        DateTimePickerField::make('published_at')
                                            ->nullable()
                                            ->helperText('When should this page be published?'),
                                    ]),
                            ]),

                        TabsField\Tab::make('SEO')
                            ->schema([
                                Section::make('Search Engine Optimization')
                                    ->collapsible(false)
                                    ->schema([
                                        TextInputField::make('meta_title')
                                            ->maxLength(70)
                                            ->helperText('Recommended: 50-70 characters'),
                                        TextAreaField::make('meta_description')
                                            ->maxLength(160)
                                            ->rows(3)
                                            ->helperText('Recommended: 150-160 characters'),
                                        TextAreaField::make('meta_keywords')
                                            ->maxLength(255)
                                            ->rows(2)
                                            ->helperText('Comma-separated keywords'),
                                        TextInputField::make('canonical_url')
                                            ->url()
                                            ->nullable(),
                                        SelectField::make('robots')
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
                    ]),
            ]);
    }
}

