<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class MapBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Map Location')
                ->description('Embed a location map')
                ->collapsible(false)
                ->schema([
                    TextInput::make('address')
                        ->label('Address')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., 123 Main St, City, Country')
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label('Location Title')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->required()
                        ->placeholder('e.g., 40.7128')
                        ->columnSpan(6),

                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->required()
                        ->placeholder('e.g., -74.0060')
                        ->columnSpan(6),

                    TextInput::make('zoom')
                        ->label('Zoom Level')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(15),
                ]),
        ];
    }
}
