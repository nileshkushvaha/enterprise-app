<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ButtonBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Button Content')
                ->description('Create a standalone button')
                ->collapsible(false)
                ->schema([
                    TextInput::make('text')
                        ->label('Button Text')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    TextInput::make('link')
                        ->label('Button Link')
                        ->required()
                        ->url()
                        ->columnSpanFull(),

                    Select::make('style')
                        ->label('Button Style')
                        ->options([
                            'primary' => 'Primary',
                            'secondary' => 'Secondary',
                            'outline' => 'Outline',
                            'ghost' => 'Ghost',
                            'danger' => 'Danger',
                        ])
                        ->default('primary')
                        ->columnSpan(6),

                    Select::make('size')
                        ->label('Button Size')
                        ->options([
                            'sm' => 'Small',
                            'md' => 'Medium',
                            'lg' => 'Large',
                        ])
                        ->default('md')
                        ->columnSpan(6),

                    Select::make('alignment')
                        ->label('Alignment')
                        ->options([
                            'left' => 'Left',
                            'center' => 'Center',
                            'right' => 'Right',
                        ])
                        ->default('left')
                        ->columnSpan(12),
                ]),
        ];
    }
}
