<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityLogInfolist
{
    /** Maps event names to Filament badge colors (mirrors ActivityLogTable). */
    private static function eventColor(?string $event): string
    {
        return match ($event) {
            'created', 'registered'                                    => 'success',
            'updated', 'roles_updated', 'profile_updated',
            'password_changed', 'photo_updated', 'role_updated',
            '2fa_enabled', '2fa_disabled', 'account_unlocked'         => 'warning',
            'deleted', 'login_failed'                                  => 'danger',
            'login', 'logout', 'password_reset', 'auto_published',
            'manually_ran', 'webhook_received', 'role_created',
            'photo_removed', 'password_reset_requested'               => 'info',
            'previewed', 'contact_form_submitted', 'media_updated'     => 'gray',
            default                                                    => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Grid::make(3)->schema([
                TextEntry::make('log_name')
                    ->label('Log Channel')
                    ->badge()
                    ->color('gray'),

                TextEntry::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state): string => self::eventColor($state)),

                TextEntry::make('created_at')
                    ->label('Performed At')
                    ->dateTime('Y-m-d H:i:s'),
            ]),

            Grid::make(2)->schema([
                Section::make('Subject')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('subject_type')
                            ->label('Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

                        TextEntry::make('subject_id')
                            ->label('ID')
                            ->default('—')
                            ->copyable(),

                        TextEntry::make('subject_link')
                            ->label('Record')
                            ->state(function (Activity $record): string {
                                if (! $record->subject_type || ! $record->subject_id) {
                                    return '—';
                                }
                                $class = $record->subject_type;
                                if (! class_exists($class)) {
                                    return class_basename($class) . ' (class not found)';
                                }
                                try {
                                    $model = (new $class)->find($record->subject_id);
                                    return $model
                                        ? class_basename($class) . ' #' . $record->subject_id . ' (exists)'
                                        : class_basename($class) . ' #' . $record->subject_id . ' (deleted)';
                                } catch (\Throwable) {
                                    return '—';
                                }
                            }),
                    ]),

                Section::make('Performed By')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('causer.name')
                            ->label('Name')
                            ->default('System / Unauthenticated'),

                        TextEntry::make('causer.email')
                            ->label('Email')
                            ->default('—')
                            ->copyable(),

                        TextEntry::make('causer_type')
                            ->label('Actor Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'System')
                            ->default('System'),
                    ]),
            ]),

            Section::make('Description')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    TextEntry::make('description')
                        ->label('')
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Tabs::make('Changes')
                ->tabs([
                    Tab::make('Before')
                        ->icon('heroicon-o-arrow-left-circle')
                        ->schema([
                            TextEntry::make('before_values')
                                ->label('')
                                ->state(function (Activity $record): string {
                                    $old = data_get($record->attribute_changes, 'old');
                                    return $old ? json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'No previous values recorded.';
                                })
                                ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                                ->extraAttributes(['class' => 'whitespace-pre-wrap break-all text-xs'])
                                ->columnSpanFull(),
                        ]),

                    Tab::make('After')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->schema([
                            TextEntry::make('after_values')
                                ->label('')
                                ->state(function (Activity $record): string {
                                    $new = data_get($record->attribute_changes, 'attributes');
                                    return $new ? json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'No new values recorded.';
                                })
                                ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                                ->extraAttributes(['class' => 'whitespace-pre-wrap break-all text-xs'])
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Metadata')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            TextEntry::make('batch_uuid')
                                ->label('Batch UUID')
                                ->default('—')
                                ->copyable()
                                ->helperText('Groups related log entries created in a single operation.'),

                            TextEntry::make('properties_ip')
                                ->label('IP Address')
                                ->state(fn (Activity $record): string => data_get($record->properties, 'ip') ?? data_get($record->properties, 'ip_address') ?? '—')
                                ->copyable(),

                            TextEntry::make('properties_ua')
                                ->label('User Agent')
                                ->state(fn (Activity $record): string => data_get($record->properties, 'user_agent') ?? '—')
                                ->limit(120)
                                ->tooltip(fn (Activity $record): string => data_get($record->properties, 'user_agent') ?? ''),

                            TextEntry::make('properties_raw')
                                ->label('Full Properties (JSON)')
                                ->state(function (Activity $record): string {
                                    $props = $record->properties?->toArray() ?? [];
                                    return $props ? json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}';
                                })
                                ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                                ->extraAttributes(['class' => 'whitespace-pre-wrap break-all text-xs'])
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
