<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class HeroBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Hero Content')
                ->description('Create a compelling hero section with image and call-to-action')
                ->collapsible(false)
                ->schema([
                    TextInput::make('title')
                        ->label('Hero Title')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter headline'),

                    Textarea::make('subtitle')
                        ->label('Subtitle')
                        ->maxLength(500)
                        ->rows(3)
                        ->placeholder('Enter supporting text'),

                    FileUpload::make('image')
                        ->label('Background Image')
                        ->image()
                        
                        ->directory('blocks/hero')
                        ->maxSize(5120),

                    TextInput::make('button_text')
                        ->label('Button Text')
                        ->placeholder('e.g., Learn More'),

                    TextInput::make('button_link')
                        ->label('Button Link')
                        ->url()
                        ->placeholder('e.g., /about'),

                    Select::make('button_style')
                        ->label('Button Style')
                        ->options([
                            'primary' => 'Primary',
                            'secondary' => 'Secondary',
                            'outline' => 'Outline',
                            'ghost' => 'Ghost',
                        ])
                        ->default('primary'),
                ]),
        ];
    }
}
