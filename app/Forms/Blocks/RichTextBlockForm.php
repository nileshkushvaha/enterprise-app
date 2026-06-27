<?php

namespace App\Forms\Blocks;

use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;

class RichTextBlockForm
{
    public static function schema(): array
    {
        return [
            Section::make('Rich Text Content')
                ->description('Add formatted text content with styling options')
                ->collapsible(false)
                ->schema([
                    RichEditor::make('text')
                        ->label('Content')
                        ->required()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('blocks/rich-text')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
