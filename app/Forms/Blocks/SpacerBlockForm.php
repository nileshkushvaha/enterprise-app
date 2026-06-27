<?php

namespace App\Forms\Blocks;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;

class SpacerBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Spacer Settings')
                ->description('Add vertical spacing between sections')
                ->collapsible(false)
                ->schema([
                    TextInput::make('height')
                        ->label('Height (pixels)')
                        ->required()
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(500)
                        ->default(60),
                ]),
        ];
    }
}
