<?php

namespace App\Filament\Resources\Countries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Core Information')
                    ->description('Primary identifiers for this country.')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Country Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. India')
                                ->helperText('Official country name.'),

                            TextInput::make('iso2')
                                ->label('ISO 2 Code')
                                ->required()
                                ->maxLength(2)
                                ->minLength(2)
                                ->unique(ignoreRecord: true)
                                // ->uppercase()
                                ->placeholder('IN')
                                ->helperText('2-letter ISO 3166-1 alpha-2 code.'),

                            TextInput::make('iso3')
                                ->label('ISO 3 Code')
                                ->maxLength(3)
                                ->minLength(3)
                                ->unique(ignoreRecord: true)
                                // ->uppercase()
                                ->nullable()
                                ->placeholder('IND')
                                ->helperText('3-letter ISO 3166-1 alpha-3 code (optional).'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('phone_code')
                                ->label('Phone Code')
                                ->maxLength(20)
                                ->nullable()
                                ->placeholder('+91')
                                ->helperText('International dialling prefix.'),

                            TextInput::make('nationality')
                                ->label('Nationality')
                                ->maxLength(100)
                                ->nullable()
                                ->placeholder('Indian')
                                ->helperText('Demonym for this country.'),

                            TextInput::make('flag')
                                ->label('Flag Emoji')
                                ->maxLength(10)
                                ->nullable()
                                ->placeholder('🇮🇳')
                                ->helperText('Paste the flag emoji directly.'),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Settings & Meta')
                    ->description('Display order, visibility, and internal notes.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->integer()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Lower value appears first in lists.'),

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')
                                ->required()
                                ->native(false)
                                ->helperText('Only active countries appear in front-end dropdowns.'),
                        ]),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->maxLength(500)
                            ->nullable()
                            ->rows(3)
                            ->placeholder('Internal notes about this country (optional).')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
