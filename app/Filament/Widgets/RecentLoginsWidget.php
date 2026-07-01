<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LoginHistory;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentLoginsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('View:RecentLoginsWidget');
    }

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Login Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LoginHistory::query()
                    ->with('user')
                    ->latest('logged_in_at')
                    ->limit(6)
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (LoginHistory $record) => $record->user?->email ?? '—')
                    ->weight(FontWeight::Medium)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'locked' => 'warning',
                        'blocked' => 'danger',
                        'unverified' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->color('gray')
                    ->copyable(),

                TextColumn::make('browser')
                    ->label('Browser')
                    ->description(fn (LoginHistory $record) => $record->platform ?? '—')
                    ->color('gray'),

                TextColumn::make('location_country')
                    ->label('Location')
                    ->description(fn (LoginHistory $record) => $record->location_city ?? '')
                    ->default('—'),

                TextColumn::make('logged_in_at')
                    ->label('Time')
                    ->since()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->striped();
    }
}
