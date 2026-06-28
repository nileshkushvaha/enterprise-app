<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FAQBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('FAQ Items')
                ->description('Add frequently asked questions and answers')
                ->collapsible(false)
                ->schema([
                    Repeater::make('items')
                        ->label('Questions & Answers')
                        ->schema([
                            TextInput::make('question')
                                ->label('Question')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('answer')
                                ->label('Answer')
                                ->required()
                                ->rows(4)
                                ->maxLength(2000)
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addAction(fn ($action) => $action->label('Add Question'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
