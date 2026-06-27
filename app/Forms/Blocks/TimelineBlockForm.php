<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class TimelineBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Timeline Items')
                ->description('Create a chronological timeline')
                ->collapsible(false)
                ->schema([
                    Repeater::make('items')
                        ->label('Timeline Events')
                        ->schema([
                            TextInput::make('title')
                                ->label('Event Title')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            TextInput::make('date')
                                ->label('Date')
                                ->required()
                                ->maxLength(100)
                                ->columnSpan(6),

                            Textarea::make('description')
                                ->label('Description')
                                ->required()
                                ->rows(4)
                                ->maxLength(500)
                                ->columnSpanFull(),
                        ])
                        ->columns(12)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addAction(fn ($action) => $action->label('Add Event'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
