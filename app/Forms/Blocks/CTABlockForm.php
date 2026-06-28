<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CTABlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('CTA Content')
                ->description('Create a call-to-action section with button')
                ->collapsible(false)
                ->schema([
                    TextInput::make('title')
                        ->label('Heading')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(500),

                    TextInput::make('button_text')
                        ->label('Button Text')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('button_link')
                        ->label('Button Link')
                        ->required()
                        ->url(),

                    Select::make('button_style')
                        ->label('Button Style')
                        ->options([
                            'primary' => 'Primary',
                            'secondary' => 'Secondary',
                            'outline' => 'Outline',
                        ])
                        ->default('primary'),
                ]),

            Section::make('Styling')
                ->collapsible(false)
                ->columns(2)
                ->schema([
                    ColorPicker::make('background_color')
                        ->label('Background Color')
                        ->default('#ffffff'),

                    ColorPicker::make('text_color')
                        ->label('Text Color')
                        ->default('#000000'),
                ]),
        ];
    }
}
