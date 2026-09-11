<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Navigation\Concerns\HasCentralizedNavigation;
use App\Filament\Navigation\Concerns\HasSettingsSectionBreadcrumb;
use App\Models\Page as PageModel;
use App\Settings\GeneralSettings;
use App\Support\Timezone\IanaTimezone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class GeneralSettingsPage extends Page
{
    use HasCentralizedNavigation;
    use HasSettingsAccess;
    use HasSettingsSectionBreadcrumb;
    use LogsSettingsUpdates;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'General';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings/general';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getLabel(): string
    {
        return 'General';
    }

    public function getTitle(): string|Htmlable
    {
        return 'General';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Name, contact details, branding, homepage and footer for the public site.';
    }

    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->form->fill([
            'app_name' => $settings->app_name,
            'app_short_name' => $settings->app_short_name,
            'organization_name' => $settings->organization_name,
            'support_email' => $settings->support_email,
            'support_phone' => $settings->support_phone,
            'website_url' => $settings->website_url,
            'address' => $settings->address,
            'logo' => $settings->logo,
            'favicon' => $settings->favicon,
            'header_top_bar_enabled' => $settings->header_top_bar_enabled,
            'facebook_url' => $settings->facebook_url,
            'instagram_url' => $settings->instagram_url,
            'x_url' => $settings->x_url,
            'youtube_url' => $settings->youtube_url,
            'default_timezone' => $settings->default_timezone,
            'default_currency' => $settings->default_currency,
            'footer_copyright' => $settings->footer_copyright,
            'footer_text' => $settings->footer_text,
            'homepage_display' => $settings->homepage_display ?? 'template',
            'homepage_id' => $settings->homepage_id,
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
                            ->label('Save changes')
                            ->submit('save')
                            ->keyBindings(['mod+s']),

                        Action::make('reset')
                            ->label('Reset defaults')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalHeading('Reset the defaults?')
                            ->modalDescription('Sets the default timezone to UTC and the currency to INR, turns the header top bar off, clears the social links, and shows the built-in homepage. Name, contact details, branding and footer text are kept. This is saved immediately.')
                            ->modalSubmitActionLabel('Reset')
                            ->action('resetDefaults'),
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
            Section::make('Organization & contact')
                ->description('Shown in the header, footer, emails and invoices.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('app_name')
                            ->label('Application name')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('SIRI Education'),
                        TextInput::make('app_short_name')
                            ->label('Short name')
                            ->maxLength(50)
                            ->placeholder('SIRI')
                            ->helperText('Used where space is tight, such as browser tabs.'),
                        TextInput::make('organization_name')
                            ->label('Organization name')
                            ->maxLength(150)
                            ->helperText('The legal or trading name, if it differs from the application name.'),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('support_email')
                            ->label('Support email')
                            ->email()
                            ->required()
                            ->maxLength(150),
                        TextInput::make('support_phone')
                            ->label('Support phone')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://example.com'),
                    ]),
                    Textarea::make('address')
                        ->label('Postal address')
                        ->rows(2)
                        ->maxLength(500),
                ]),

            Section::make('Branding')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->directory('settings/branding')
                            ->imagePreviewHeight('80')
                            ->helperText('PNG, JPG or SVG, up to 2 MB.'),
                        FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->acceptedFileTypes(['image/x-icon', 'image/png'])
                            ->maxSize(512)
                            ->directory('settings/branding')
                            ->imagePreviewHeight('80')
                            ->helperText('ICO or PNG, up to 512 KB.'),
                    ]),
                ]),

            Section::make('Defaults')
                ->description('Used when nothing more specific applies. The default country and locale switching are under Platform.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('default_timezone')
                            ->label('Default timezone')
                            ->options(
                                collect(IanaTimezone::identifiers())
                                    ->mapWithKeys(fn (string $tz): array => [$tz => $tz])
                                    ->all()
                            )
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->helperText('For users who have not set their own timezone.'),
                        Select::make('default_currency')
                            ->label('Default currency')
                            ->options([
                                'INR' => 'INR — Indian Rupee (₹)',
                                'USD' => 'USD — US Dollar ($)',
                                'EUR' => 'EUR — Euro (€)',
                                'GBP' => 'GBP — British Pound (£)',
                                'AED' => 'AED — UAE Dirham (د.إ)',
                                'SGD' => 'SGD — Singapore Dollar (S$)',
                                'AUD' => 'AUD — Australian Dollar (A$)',
                                'CAD' => 'CAD — Canadian Dollar (C$)',
                            ])
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->helperText('Prices are set per country; this applies where no country price exists.'),
                    ]),
                ]),

            Section::make('Homepage')
                ->description('What visitors see at the site root.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('homepage_display')
                            ->label('Homepage shows')
                            ->options([
                                'template' => 'The built-in homepage',
                                'static_page' => 'A published page',
                            ])
                            ->native(false)
                            ->live()
                            ->required(),
                        Select::make('homepage_id')
                            ->label('Page')
                            ->placeholder('Select a published page')
                            ->options(fn () => PageModel::query()
                                ->published()
                                ->orderBy('title')
                                ->get()
                                ->mapWithKeys(fn ($page) => [(string) $page->id => $page->title])
                                ->all()
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(fn ($get) => $get('homepage_display') === 'static_page')
                            ->required(fn ($get) => $get('homepage_display') === 'static_page')
                            ->helperText('Only published pages can be chosen. Unpublishing it later falls back to the built-in homepage.'),
                    ]),
                ]),

            Section::make('Footer')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('footer_copyright')
                        ->label('Copyright line')
                        ->maxLength(255)
                        ->placeholder('© {year} {name}. All rights reserved.')
                        ->helperText('Leave empty for the default line. {year} and {name} are filled in automatically.'),
                    Textarea::make('footer_text')
                        ->label('Additional footer text')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Optional, for example a legal disclaimer.'),
                ]),

            Section::make('Header top bar')
                ->description('A strip above the public navigation with the support phone and email, and optional social links.')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn ($get): bool => ! (bool) $get('header_top_bar_enabled'))
                ->schema([
                    Toggle::make('header_top_bar_enabled')
                        ->label('Show the header top bar')
                        ->live(),
                    Grid::make(4)->schema([
                        TextInput::make('facebook_url')
                            ->label('Facebook')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://facebook.com/…'),
                        TextInput::make('instagram_url')
                            ->label('Instagram')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://instagram.com/…'),
                        TextInput::make('x_url')
                            ->label('X')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://x.com/…'),
                        TextInput::make('youtube_url')
                            ->label('YouTube')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://youtube.com/@…'),
                    ])->visible(fn ($get) => (bool) $get('header_top_bar_enabled')),
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

        $saved = $this->saveSettingsWithAudit(GeneralSettings::class, 'settings', function (GeneralSettings $settings) use ($data): void {
            $settings->app_name = $data['app_name'];
            $settings->app_short_name = $data['app_short_name'] ?? null;
            $settings->organization_name = $data['organization_name'] ?? null;
            $settings->support_email = $data['support_email'];
            $settings->support_phone = $data['support_phone'] ?? null;
            $settings->website_url = $data['website_url'] ?? null;
            $settings->address = $data['address'] ?? null;
            $settings->logo = $data['logo'] ?? $settings->logo;
            $settings->favicon = $data['favicon'] ?? $settings->favicon;
            $settings->header_top_bar_enabled = (bool) ($data['header_top_bar_enabled'] ?? false);
            $settings->facebook_url = $data['facebook_url'] ?? null;
            $settings->instagram_url = $data['instagram_url'] ?? null;
            $settings->x_url = $data['x_url'] ?? null;
            $settings->youtube_url = $data['youtube_url'] ?? null;
            $settings->default_timezone = $data['default_timezone'];
            $settings->default_currency = $data['default_currency'];
            $settings->footer_copyright = $data['footer_copyright'] ?? null;
            $settings->footer_text = $data['footer_text'] ?? null;
            $settings->homepage_display = $data['homepage_display'] ?? 'template';
            $settings->homepage_id = ($data['homepage_display'] ?? 'template') === 'static_page'
                                            ? ($data['homepage_id'] ?? null)
                                            : null;
        });

        if (! $saved) {
            return;
        }

        Notification::make()
            ->title('General settings saved')
            ->success()
            ->send();
    }

    public function resetDefaults(): void
    {
        $saved = $this->saveSettingsWithAudit(GeneralSettings::class, 'settings', function (GeneralSettings $settings): void {
            // TZ-1: "reset to defaults" must not reinstate an
            // India-specific platform timezone on a multi-country
            // deployment. This setting IS the platform default tier, so
            // there is nothing above it to inherit — reset lands on the
            // neutral final fallback and an operator sets their real
            // platform timezone explicitly.
            $settings->default_timezone = IanaTimezone::FALLBACK;
            $settings->default_currency = 'INR';
            $settings->header_top_bar_enabled = false;
            $settings->facebook_url = null;
            $settings->instagram_url = null;
            $settings->x_url = null;
            $settings->youtube_url = null;
            $settings->homepage_display = 'template';
            $settings->homepage_id = null;
        });

        if (! $saved) {
            return;
        }

        $this->mount(); // reload form

        Notification::make()
            ->title('Settings reset to defaults')
            ->success()
            ->send();
    }
}
