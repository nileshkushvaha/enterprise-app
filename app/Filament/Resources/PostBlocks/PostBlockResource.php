<?php

namespace App\Filament\Resources\PostBlocks;

use App\Filament\Resources\PostBlocks\Pages\CreatePostBlock;
use App\Filament\Resources\PostBlocks\Pages\EditPostBlock;
use App\Filament\Resources\PostBlocks\Pages\ListPostBlocks;
use App\Filament\Resources\PostBlocks\Schemas\PostBlockForm;
use App\Filament\Resources\PostBlocks\Tables\PostBlocksTable;
use App\Models\PostBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PostBlockResource extends Resource
{
    protected static ?string $model = PostBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Post Blocks';

    protected static ?string $modelLabel = 'Post Block';

    protected static ?string $pluralModelLabel = 'Post Blocks';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return PostBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostBlocks::route('/'),
            'create' => CreatePostBlock::route('/create'),
            'edit' => EditPostBlock::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', PostBlock::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', PostBlock::class) ?? false;
    }
}
