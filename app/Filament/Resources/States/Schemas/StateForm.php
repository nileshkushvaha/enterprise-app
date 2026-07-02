<?php

namespace App\Filament\Resources\States\Schemas;

use App\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Core Information')
                    ->description('Primary identifiers for this state.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(fn () => Country::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->helperText('The country this state/province belongs to.')
                            ->columnSpanFull(),

                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('State Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Maharashtra')
                                ->helperText('Official state/province name.'),

                            TextInput::make('code')
                                ->label('State Code')
                                ->maxLength(20)
                                ->nullable()
                                ->placeholder('MH')
                                ->helperText('Short code, e.g. DL, MH, CA, TX.'),

                            TextInput::make('iso_code')
                                ->label('ISO Code')
                                ->maxLength(20)
                                ->nullable()
                                ->placeholder('IN-MH')
                                ->helperText('ISO 3166-2 code, e.g. IN-DL, US-CA.'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('capital')
                                ->label('Capital')
                                ->maxLength(255)
                                ->nullable()
                                ->placeholder('e.g. Mumbai'),

                            TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->nullable()
                                ->placeholder('19.07283000'),

                            TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->nullable()
                                ->placeholder('72.88261000'),
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
                                ->helperText('Only active states appear in front-end dropdowns.'),
                        ]),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->maxLength(500)
                            ->nullable()
                            ->rows(3)
                            ->placeholder('Internal notes about this state (optional).')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
