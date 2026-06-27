<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class MailSettingsPage extends Page
{
    use HasSettingsAccess;

    protected static string|BackedEnum|null $navigationIcon  = Heroicon::OutlinedEnvelope;
    protected static ?string $navigationLabel                = 'Mail';
    protected static string|\UnitEnum|null $navigationGroup                = 'Configuration';
    protected static ?int    $navigationSort                 = 3;
    protected static ?string $slug                           = 'settings/mail';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $testEmail = null;

    public static function getLabel(): string
    {
        return 'Mail Settings';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Mail Settings';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Configure SMTP settings for outgoing email. Passwords are encrypted before storage.';
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin'     => 'Dashboard',
            '/admin/settings/general' => 'Settings',
            '#'          => 'Mail',
        ];
    }

    public function mount(): void
    {
        $settings = app(MailSettings::class);

        $this->form->fill([
            'from_name'          => $settings->from_name,
            'from_email'         => $settings->from_email,
            'driver'             => $settings->driver,
            'host'               => $settings->host,
            'port'               => $settings->port,
            'username'           => $settings->username,
            'password'           => null, // never prefill password
            'encryption'         => $settings->encryption,
            'queue_emails'       => $settings->queue_emails,
            'connection_timeout' => $settings->connection_timeout,
            'retry_attempts'     => $settings->retry_attempts,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            FormComponent::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    ActionsComponent::make([
                        Action::make('save')
                            ->label('Save Mail Settings')
                            ->submit('save')
                            ->keyBindings(['mod+s']),

                        Action::make('test')
                            ->label('Send Test Email')
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->color('info')
                            ->requiresConfirmation()
                            ->modalHeading('Send Test Email')
                            ->modalDescription('This will send a test email using the currently saved settings.')
                            ->form([
                                TextInput::make('test_email')
                                    ->label('Send test to')
                                    ->email()
                                    ->required()
                                    ->default(fn () => auth()->user()?->email),
                            ])
                            ->action(function (array $data) {
                                $this->sendTestEmail($data['test_email']);
                            }),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            // ── Sender Information ────────────────────────────────────
            Section::make('Sender Information')
                ->description('The name and email address shown to recipients.')
                ->aside()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('from_name')
                            ->label('From Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Sphere Education'),

                        TextInput::make('from_email')
                            ->label('From Email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->placeholder('noreply@example.com'),
                    ]),
                ]),

            // ── SMTP Configuration ────────────────────────────────────
            Section::make('SMTP Configuration')
                ->description('Connection settings for your mail server.')
                ->aside()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('driver')
                            ->label('Mail Driver')
                            ->options([
                                'smtp'     => 'SMTP',
                                'sendmail' => 'Sendmail',
                                'log'      => 'Log (development)',
                                'array'    => 'Array (testing)',
                            ])
                            ->native(false)
                            ->required()
                            ->live(),

                        Select::make('encryption')
                            ->label('Encryption')
                            ->options([
                                'tls'  => 'TLS (recommended)',
                                'ssl'  => 'SSL',
                                'none' => 'None',
                            ])
                            ->native(false)
                            ->required(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('host')
                            ->label('SMTP Host')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('smtp.mailtrap.io'),

                        TextInput::make('port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('587'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->maxLength(255)
                            ->autocomplete('off'),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->autocomplete('new-password')
                            ->helperText('Leave blank to keep the existing password. Stored encrypted.')
                            ->dehydrated(fn ($state) => filled($state)),
                    ]),
                ]),

            // ── Queue ─────────────────────────────────────────────────
            Section::make('Email Queue')
                ->description('Control whether emails are sent synchronously or queued.')
                ->aside()
                ->schema([
                    Toggle::make('queue_emails')
                        ->label('Queue Emails')
                        ->helperText('Send emails asynchronously via the queue worker. Requires a running queue worker.')
                        ->onColor('success'),
                ]),

            // ── Advanced ──────────────────────────────────────────────
            Section::make('Advanced')
                ->description('Timeout and retry configuration.')
                ->aside()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('connection_timeout')
                            ->label('Connection Timeout (seconds)')
                            ->numeric()
                            ->required()
                            ->minValue(5)
                            ->maxValue(300)
                            ->default(30),

                        TextInput::make('retry_attempts')
                            ->label('Retry Attempts')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(3),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (Halt) {
            return;
        }

        $settings = app(MailSettings::class);

        $settings->from_name          = $data['from_name'];
        $settings->from_email         = $data['from_email'];
        $settings->driver             = $data['driver'];
        $settings->host               = $data['host'];
        $settings->port               = (int) $data['port'];
        $settings->username           = $data['username'] ?? null;
        $settings->encryption         = $data['encryption'];
        $settings->queue_emails       = (bool) ($data['queue_emails'] ?? false);
        $settings->connection_timeout = (int) ($data['connection_timeout'] ?? 30);
        $settings->retry_attempts     = (int) ($data['retry_attempts'] ?? 3);

        // Only update password if a new one was provided — encrypt it
        if (filled($data['password'] ?? null)) {
            $settings->password = Crypt::encryptString($data['password']);
        }

        $settings->save();

        Notification::make()
            ->title('Mail settings saved')
            ->success()
            ->send();
    }

    public function sendTestEmail(string $to): void
    {
        try {
            $settings = app(MailSettings::class);

            // Temporarily override mail config with saved settings
            config([
                'mail.mailers.smtp.host'       => $settings->host,
                'mail.mailers.smtp.port'        => $settings->port,
                'mail.mailers.smtp.username'    => $settings->username,
                'mail.mailers.smtp.password'    => $settings->password
                    ? Crypt::decryptString($settings->password)
                    : null,
                'mail.mailers.smtp.encryption'  => $settings->encryption === 'none' ? null : $settings->encryption,
                'mail.from.address'             => $settings->from_email,
                'mail.from.name'                => $settings->from_name,
            ]);

            Mail::raw(
                'This is a test email from ' . app(GeneralSettings::class)->app_name . '. Your mail configuration is working correctly.',
                function ($message) use ($to, $settings) {
                    $message->to($to)
                        ->from($settings->from_email, $settings->from_name)
                        ->subject('Test Email — Mail Configuration');
                }
            );

            Notification::make()
                ->title('Test email sent')
                ->body("A test email was sent to {$to}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to send test email')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
