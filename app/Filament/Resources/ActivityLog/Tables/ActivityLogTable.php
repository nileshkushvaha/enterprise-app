<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created'       => 'success',
                        'updated'       => 'warning',
                        'deleted'       => 'danger',
                        'media_updated' => 'info',
                        default         => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (Activity $record): string => $record->description),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state
                        ? class_basename($state) . ' #' . ($record->subject_id ?? '—')
                        : '—'
                    )
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->since()
                    ->tooltip(fn (Activity $record): string => $record->created_at->format('Y-m-d H:i:s')),
            ])

            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(fn (): array => \Spatie\Activitylog\Models\Activity::query()
                        ->distinct()
                        ->whereNotNull('event')
                        ->pluck('event', 'event')
                        ->mapWithKeys(fn ($v) => [$v => ucfirst($v)])
                        ->toArray()
                    )
                    ->placeholder('All events'),

                SelectFilter::make('log_name')
                    ->label('Log Channel')
                    ->options(fn (): array => \Spatie\Activitylog\Models\Activity::query()
                        ->distinct()
                        ->whereNotNull('log_name')
                        ->pluck('log_name', 'log_name')
                        ->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])
                        ->toArray()
                    )
                    ->placeholder('All channels'),

                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(fn (): array => \Spatie\Activitylog\Models\Activity::query()
                        ->distinct()
                        ->whereNotNull('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($v) => [$v => class_basename($v)])
                        ->toArray()
                    )
                    ->placeholder('All subjects'),

                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => \App\Models\User::query()
                        ->whereIn('id', \Spatie\Activitylog\Models\Activity::query()
                            ->where('causer_type', \App\Models\User::class)
                            ->whereNotNull('causer_id')
                            ->distinct()
                            ->pluck('causer_id')
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->searchable()
                    ->placeholder('All users'),

                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From')->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'])  $indicators[] = 'From: '  . $data['from'];
                        if ($data['until']) $indicators[] = 'Until: ' . $data['until'];
                        return $indicators;
                    }),
            ])

            ->recordAction('view')
            ->actions([
                ViewAction::make()->label(''),
            ])
            ->bulkActions([])
            ->paginated([15, 25, 50, 100])
            ->poll(null);
    }
}
