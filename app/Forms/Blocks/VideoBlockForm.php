<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class VideoBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Video Content')
                ->description('Embed a video from YouTube, Vimeo, or upload directly')
                ->collapsible(false)
                ->schema([
                    TextInput::make('video_url')
                        ->label('Video URL')
                        ->required()
                        ->url()
                        ->placeholder('e.g., https://youtube.com/watch?v=...'),

                    FileUpload::make('thumbnail')
                        ->label('Custom Thumbnail')
                        ->image()
                        ->directory('blocks/video')
                        ->maxSize(2048),

                    Textarea::make('caption')
                        ->label('Video Caption')
                        ->rows(2)
                        ->placeholder('Optional caption text'),
                ]),
        ];
    }
}
