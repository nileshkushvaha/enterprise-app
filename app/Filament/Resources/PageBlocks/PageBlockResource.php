<?php

namespace App\Filament\Resources\PageBlocks;

use App\Filament\Resources\PageBlocks\Pages\CreatePageBlock;
use App\Filament\Resources\PageBlocks\Pages\EditPageBlock;
use App\Filament\Resources\PageBlocks\Pages\ListPageBlocks;
use App\Filament\Resources\PageBlocks\Schemas\PageBlockForm;
use App\Filament\Resources\PageBlocks\Tables\PageBlocksTable;
use App\Content\Models\ContentBlock;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PageBlockResource extends Resource
{
    protected static ?string $model = ContentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Content Blocks';

    protected static ?string $modelLabel = 'Content Block';

    protected static ?string $pluralModelLabel = 'Content Blocks';

    protected static string|\UnitEnum|null $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return PageBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageBlocks::route('/'),
            'create' => CreatePageBlock::route('/create'),
            'edit' => EditPageBlock::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('blockable_type', (new Page)->getMorphClass());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->where('blockable_type', (new Page)->getMorphClass())
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', ContentBlock::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', ContentBlock::class) ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
