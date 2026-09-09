<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Navigation\Concerns\HasCentralizedNavigation;
use App\Filament\Resources\EmailLogs\Pages\ListEmailLogs;
use App\Filament\Resources\EmailLogs\Pages\ViewEmailLog;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmailLogResource extends Resource
{
    use HasCentralizedNavigation;

    protected static ?string $model = EmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email Logs';

    protected static ?string $modelLabel = 'Email Log';

    protected static ?string $pluralModelLabel = 'Email Logs';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'email-logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')->badge(),
            TextEntry::make('category')->badge(),
            TextEntry::make('provider')
                ->label('Delivery Provider')
                ->helperText('Which email service actually sent this message.')
                ->badge(),
            TextEntry::make('mailer')
                ->label('Mailer')
                ->helperText('The mail configuration used when sending.')
                ->badge(),
            TextEntry::make('subject')->columnSpanFull(),
            TextEntry::make('from_address')->label('From'),
            TextEntry::make('from_name'),
            // Recipients are stored as a LIST of {address, name} objects
            // (EmailLogService::addresses()), not a flat map. Rendering
            // them with KeyValueEntry handed Filament an array where it
            // expected a string and took the whole page down with
            // "htmlspecialchars(): must be of type string, array given".
            self::recipientEntry('to', 'To'),
            self::recipientEntry('cc', 'CC'),
            self::recipientEntry('bcc', 'BCC'),
            TextEntry::make('notification_type')
                ->label('Notification Type')
                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? class_basename($state) : null)
                ->columnSpanFull(),
            TextEntry::make('notification_id'),
            TextEntry::make('provider_message_id'),
            TextEntry::make('error')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('sent_at')->dateTime(),
            TextEntry::make('delivered_at')->dateTime(),
            TextEntry::make('failed_at')->dateTime(),
            // Metadata is nested (laravel_mail_data_keys => [...]), which
            // KeyValueEntry cannot render either — same crash, different
            // column, and this one fires for EVERY real message.
            KeyValueEntry::make('metadata')
                ->state(fn (EmailLog $record): array => self::flattenForDisplay($record->metadata ?? []))
                ->columnSpanFull(),
        ]);
    }

    /**
     * Key/value display needs scalar values; anything nested is shown as
     * compact JSON rather than crashing the page.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function flattenForDisplay(array $values): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $flat[(string) $key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => '—',
                is_scalar($value) => (string) $value,
                default => (string) json_encode($value, JSON_UNESCAPED_SLASHES),
            };
        }

        return $flat;
    }

    /**
     * One recipient per line, as "Name <address>" — or just the address
     * when the sender supplied no name.
     */
    private static function recipientEntry(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(fn (EmailLog $record): array => collect($record->{$name} ?? [])
                ->map(function (mixed $recipient): ?string {
                    if (is_string($recipient)) {
                        return $recipient;
                    }

                    if (! is_array($recipient)) {
                        return null;
                    }

                    $address = (string) ($recipient['address'] ?? '');
                    $person = trim((string) ($recipient['name'] ?? ''));

                    return match (true) {
                        $address === '' => null,
                        $person === '' => $address,
                        default => sprintf('%s <%s>', $person, $address),
                    };
                })
                ->filter()
                ->values()
                ->all())
            ->listWithLineBreaks()
            ->placeholder('—')
            ->columnSpanFull();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent', 'delivered' => 'success',
                        'failed', 'bounced', 'complained', 'suppressed' => 'danger',
                        'delayed' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('subject')->searchable()->limit(48),
                TextColumn::make('to')
                    ->label('To')
                    ->state(fn (EmailLog $record): string => collect($record->to ?? [])
                        ->pluck('address')
                        ->filter()
                        ->implode(', ')),
                TextColumn::make('provider')->badge()->toggleable(),
                TextColumn::make('provider_message_id')->searchable()->toggleable(),
                TextColumn::make('created_at')->dateTime('M j, Y H:i')->sortable()->since(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'sent' => 'Sent',
                    'delivered' => 'Delivered',
                    'delayed' => 'Delayed',
                    'failed' => 'Failed',
                    'bounced' => 'Bounced',
                    'complained' => 'Complained',
                    'suppressed' => 'Suppressed',
                ]),
                SelectFilter::make('category')->options([
                    'auth' => 'Auth',
                    'booking' => 'Booking',
                    'payment' => 'Payment',
                    'tutor' => 'Tutor',
                    'wallet' => 'Wallet',
                    'support' => 'Support',
                    'admin' => 'Admin',
                    'general' => 'General',
                ]),
                SelectFilter::make('provider')->options([
                    'resend' => 'Resend',
                    'log' => 'Log',
                    'array' => 'Array',
                    'smtp' => 'SMTP',
                ]),
            ])
            ->recordAction('view')
            ->actions([
                ViewAction::make()->label(''),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLogs::route('/'),
            'view' => ViewEmailLog::route('/{record}'),
        ];
    }
}
