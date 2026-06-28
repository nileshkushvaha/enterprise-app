<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ContactInfoBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Section Heading')
                ->collapsible(false)
                ->schema([
                    TextInput::make('eyebrow')
                        ->label('Eyebrow label')
                        ->placeholder('e.g. Keep in Touch')
                        ->maxLength(100)
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label('Heading')
                        ->placeholder('e.g. Get In Touch')
                        ->maxLength(200)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Sub-description')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Contact Cards')
                ->description('Add phone, email, address or any contact detail card.')
                ->collapsible(false)
                ->schema([
                    Repeater::make('items')
                        ->label('Cards')
                        ->schema([
                            Select::make('icon')
                                ->label('Icon')
                                ->options([
                                    'phone' => '📞 Phone',
                                    'email' => '✉️ Email',
                                    'location' => '📍 Address / Location',
                                    'clock' => '🕐 Hours',
                                    'globe' => '🌐 Website',
                                    'chat' => '💬 Chat',
                                ])
                                ->default('phone')
                                ->required()
                                ->columnSpan(4),

                            TextInput::make('label')
                                ->label('Card label')
                                ->placeholder('e.g. Phone Support')
                                ->maxLength(80)
                                ->required()
                                ->columnSpan(8),

                            TextInput::make('value')
                                ->label('Value / content')
                                ->placeholder('e.g. +1 (555) 123-4567')
                                ->maxLength(300)
                                ->required()
                                ->columnSpan(8),

                            TextInput::make('link')
                                ->label('Link (optional)')
                                ->placeholder('tel:+15551234567 or mailto:…')
                                ->maxLength(300)
                                ->columnSpan(4),
                        ])
                        ->columns(12)
                        ->minItems(1)
                        ->defaultItems(3)
                        ->addAction(fn ($action) => $action->label('Add Card'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
