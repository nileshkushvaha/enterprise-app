<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class TestimonialsBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Testimonial Grid')
                ->collapsible(false)
                ->schema([
                    Select::make('columns')
                        ->label('Grid Columns')
                        ->options([
                            2 => '2 Columns',
                            3 => '3 Columns',
                            4 => '4 Columns',
                        ])
                        ->default(3),
                ]),

            Section::make('Testimonials')
                ->description('Add customer testimonials')
                ->collapsible(false)
                ->schema([
                    Repeater::make('testimonials')
                        ->label('Testimonials')
                        ->schema([
                            Textarea::make('text')
                                ->label('Testimonial Text')
                                ->required()
                                ->rows(4)
                                ->maxLength(500)
                                ->columnSpanFull(),

                            TextInput::make('author')
                                ->label('Author Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            TextInput::make('role')
                                ->label('Author Role/Company')
                                ->maxLength(255)
                                ->columnSpan(6),

                            Select::make('rating')
                                ->label('Rating')
                                ->options([
                                    5 => '⭐⭐⭐⭐⭐ (5 Stars)',
                                    4 => '⭐⭐⭐⭐ (4 Stars)',
                                    3 => '⭐⭐⭐ (3 Stars)',
                                ])
                                ->default(5),
                        ])
                        ->columns(12)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addAction(fn ($action) => $action->label('Add Testimonial'))
                        ->deleteAction(fn ($action) => $action->requiresConfirmation())
                        ->columnSpanFull(),
                ]),
        ];
    }
}
