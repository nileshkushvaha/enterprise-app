<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class TabsBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Tab Items')
                ->description('Create tabbed content sections')
                ->collapsible(false)
                ->schema([
                    Repeater::make('items')
                        ->label('Tabs')
                        ->schema([
                            TextInput::make('title')
                                ->label('Tab Title')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('content')
                                ->label('Tab Content')
                                ->required()
                                ->rows(4)
                                ->maxLength(2000)
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->minItems(1)
                        ->defaultItems(2)
                        ->addAction(fn ($action) => $action->label('Add Tab'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
