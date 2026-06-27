<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class TeamBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Team Header')
                ->collapsible(false)
                ->schema([
                    TextInput::make('title')
                        ->label('Section Title')
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Section Description')
                        ->rows(2)
                        ->maxLength(500),

                    Select::make('columns')
                        ->label('Grid Columns')
                        ->options([
                            2 => '2 Columns',
                            3 => '3 Columns',
                            4 => '4 Columns',
                        ])
                        ->default(3),
                ]),

            Section::make('Team Members')
                ->description('Add team member profiles')
                ->collapsible(false)
                ->schema([
                    Repeater::make('members')
                        ->label('Members')
                        ->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            TextInput::make('role')
                                ->label('Role')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            FileUpload::make('image')
                                ->label('Photo')
                                ->image()
                                ->directory('blocks/team')
                                ->maxSize(2048)
                                ->columnSpanFull(),

                            Textarea::make('bio')
                                ->label('Bio')
                                ->rows(3)
                                ->maxLength(500)
                                ->columnSpanFull(),

                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->columnSpan(6),

                            TextInput::make('phone')
                                ->label('Phone')
                                ->tel()
                                ->columnSpan(6),
                        ])
                        ->columns(12)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addAction(fn ($action) => $action->label('Add Member'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
