<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class AccordionBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Accordion Items')
                ->description('Create expandable accordion sections')
                ->collapsible(false)
                ->schema([
                    Toggle::make('single_open')
                        ->label('Only allow one item open at a time')
                        ->default(true),

                    Repeater::make('items')
                        ->label('Accordion Items')
                        ->schema([
                            TextInput::make('title')
                                ->label('Section Title')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('content')
                                ->label('Section Content')
                                ->required()
                                ->rows(4)
                                ->maxLength(2000)
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addAction(fn ($action) => $action->label('Add Item'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
