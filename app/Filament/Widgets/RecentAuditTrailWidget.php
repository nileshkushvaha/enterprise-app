<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ActivityActorType;
use App\Models\Activity;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentAuditTrailWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('View:RecentAuditTrailWidget');
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Audit Trail';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with('causer')
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                BadgeColumn::make('actor_type')
                    ->label('Actor')
                    ->colors([
                        'info' => ActivityActorType::User->value,
                        'warning' => ActivityActorType::Guest->value,
                        'gray' => ActivityActorType::System->value,
                    ])
                    ->icons([
                        'heroicon-o-user' => ActivityActorType::User->value,
                        'heroicon-o-globe-alt' => ActivityActorType::Guest->value,
                        'heroicon-o-cog-6-tooth' => ActivityActorType::System->value,
                    ])
                    ->formatStateUsing(fn (?ActivityActorType $state): string => $state?->label() ?? '—'),

                TextColumn::make('actor_name')
                    ->label('By')
                    ->state(fn (Activity $record): string => $record->actorName())
                    ->description(fn (Activity $record): ?string => $record->actorEmail())
                    ->weight(FontWeight::Medium),

                TextColumn::make('log_name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('description')
                    ->label('Action')
                    ->limit(55)
                    ->tooltip(fn (Activity $record): string => $record->description),

                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->striped();
    }
}
