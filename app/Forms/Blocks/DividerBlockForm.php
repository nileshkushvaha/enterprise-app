<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DividerBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Divider Settings')
                ->description('Create a horizontal divider line')
                ->collapsible(false)
                ->schema([
                    Select::make('style')
                        ->label('Line Style')
                        ->options([
                            'solid' => 'Solid',
                            'dashed' => 'Dashed',
                            'dotted' => 'Dotted',
                            'double' => 'Double',
                        ])
                        ->default('solid'),

                    ColorPicker::make('color')
                        ->label('Color')
                        ->default('#e5e7eb'),

                    TextInput::make('width')
                        ->label('Width (%)')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(100)
                        ->default(100),
                ]),
        ];
    }
}
