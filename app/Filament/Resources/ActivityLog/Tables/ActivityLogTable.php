<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

class ActivityLogTable
{
    /** Maps event names to Filament badge colors. */
    private static function eventColor(?string $event): string
    {
        return match ($event) {
            'created', 'registered' => 'success',
            'updated', 'roles_updated', 'profile_updated',
            'password_changed', 'photo_updated', 'role_updated',
            '2fa_enabled', '2fa_disabled', 'account_unlocked' => 'warning',
            'deleted', 'login_failed' => 'danger',
            'login', 'logout', 'password_reset', 'auto_published',
            'manually_ran', 'webhook_received', 'role_created',
            'photo_removed', 'password_reset_requested' => 'info',
            'previewed', 'contact_form_submitted', 'media_updated' => 'gray',
            default => 'gray',
        };
    }

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
                    ->color(fn (?string $state): string => self::eventColor($state))
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (Activity $record): string => $record->description),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state
                        ? class_basename($state).' #'.($record->subject_id ?? '—')
                        : '—'
                    )
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Activity $record): ?string => $record->causer?->email),

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
                    ->options(fn (): array => Cache::remember(
                        'activity_log_filter_events',
                        300,
                        fn (): array => Activity::query()
                            ->distinct()
                            ->whereNotNull('event')
                            ->pluck('event', 'event')
                            ->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])
                            ->toArray()
                    ))
                    ->placeholder('All events'),

                SelectFilter::make('log_name')
                    ->label('Log Channel')
                    ->options(fn (): array => Cache::remember(
                        'activity_log_filter_channels',
                        300,
                        fn (): array => Activity::query()
                            ->distinct()
                            ->whereNotNull('log_name')
                            ->pluck('log_name', 'log_name')
                            ->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])
                            ->toArray()
                    ))
                    ->placeholder('All channels'),

                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(fn (): array => Cache::remember(
                        'activity_log_filter_subjects',
                        300,
                        fn (): array => Activity::query()
                            ->distinct()
                            ->whereNotNull('subject_type')
                            ->pluck('subject_type', 'subject_type')
                            ->mapWithKeys(fn ($v) => [$v => class_basename($v)])
                            ->toArray()
                    ))
                    ->placeholder('All subjects'),

                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Activity::query()
                            ->where('causer_type', User::class)
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
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from']) {
                            $indicators[] = 'From: '.$data['from'];
                        }
                        if ($data['until']) {
                            $indicators[] = 'Until: '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])

            ->recordAction('view')
            ->actions([
                ViewAction::make()->label(''),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll(null);
    }
}
