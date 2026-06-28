<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class StatisticsBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Statistics Grid')
                ->collapsible(false)
                ->schema([
                    Select::make('columns')
                        ->label('Grid Columns')
                        ->options([
                            2 => '2 Columns',
                            3 => '3 Columns',
                            4 => '4 Columns',
                        ])
                        ->default(4),
                ]),

            Section::make('Statistics')
                ->description('Add statistics and metrics')
                ->collapsible(false)
                ->schema([
                    Repeater::make('stats')
                        ->label('Statistics')
                        ->schema([
                            TextInput::make('number')
                                ->label('Number/Value')
                                ->required()
                                ->maxLength(50)
                                ->columnSpan(6),

                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            Textarea::make('description')
                                ->label('Description')
                                ->rows(2)
                                ->maxLength(300)
                                ->columnSpanFull(),
                        ])
                        ->columns(12)
                        ->minItems(1)
                        ->defaultItems(3)
                        ->addAction(fn ($action) => $action->label('Add Statistic'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
