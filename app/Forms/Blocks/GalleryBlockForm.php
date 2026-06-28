<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class GalleryBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Gallery Images')
                ->description('Upload multiple images for a gallery grid')
                ->collapsible(false)
                ->schema([
                    FileUpload::make('images')
                        ->label('Gallery Images')
                        ->required()
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->directory('blocks/gallery')
                        ->maxSize(5120)
                        ->minFiles(1)
                        ->columnSpanFull(),

                    Select::make('columns')
                        ->label('Grid Columns')
                        ->options([
                            2 => '2 Columns',
                            3 => '3 Columns',
                            4 => '4 Columns',
                            6 => '6 Columns',
                        ])
                        ->default(3),

                    Select::make('gap')
                        ->label('Gap Between Images')
                        ->options([
                            'sm' => 'Small',
                            'md' => 'Medium',
                            'lg' => 'Large',
                        ])
                        ->default('md'),
                ]),
        ];
    }
}
