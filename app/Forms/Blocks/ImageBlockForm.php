<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ImageBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Image Content')
                ->description('Add an image with caption and alt text')
                ->collapsible(false)
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->required()
                        ->image()

                        ->directory('blocks/image')
                        ->maxSize(5120),

                    Textarea::make('caption')
                        ->label('Image Caption')
                        ->rows(2)
                        ->placeholder('Optional caption text'),

                    TextInput::make('alt_text')
                        ->label('Alt Text (SEO)')
                        ->maxLength(255)
                        ->placeholder('Describe the image for accessibility'),
                ]),
        ];
    }
}
