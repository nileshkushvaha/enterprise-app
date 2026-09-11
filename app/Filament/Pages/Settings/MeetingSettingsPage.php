<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Booking\Enums\GoogleMeetSpaceAccess;
use App\Booking\Meetings\ZoomMeetingProvider;
use App\Booking\Services\GoogleCalendarConfigurationService;
use App\Booking\Services\MeetingJoinHandoffService;
use App\Booking\Services\RecordingAvailabilityResolver;
use App\Booking\Services\ZoomConfigurationService;
use App\Booking\Services\ZoomHostCapacityPreflightService;
use App\Filament\Navigation\Concerns\HasCentralizedNavigation;
use App\Filament\Navigation\Concerns\HasSettingsSectionBreadcrumb;
use App\Settings\MeetingSettings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Admin home for everything meeting-related: the platform switches,
 * join links, meeting creation, recording, and the two provider
 * integrations (Google Meet, Zoom). Thin by design — every rule the
 * page enforces on save is one an underlying service also enforces.
 */
class MeetingSettingsPage extends Page
{
    use HasCentralizedNavigation;
    use HasSettingsAccess;
    use HasSettingsSectionBreadcrumb;
    use LogsSettingsUpdates;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $navigationLabel = 'Meetings';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'settings/meetings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getLabel(): string
    {
        return 'Meeting Settings';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Meeting Settings';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'How lesson meetings are created, joined, recorded, and which provider runs them.';
    }

    public function mount(): void
    {
        $meeting = app(MeetingSettings::class);

        // Secrets are never rendered back: those fields start blank and a
        // blank submit keeps the stored value.
        $this->form->fill([
            'meetings_enabled' => $meeting->meetings_enabled,
            'default_provider' => $meeting->default_provider,
            'manual_provider_enabled' => $meeting->manual_provider_enabled,
            'platform_meeting_account' => $meeting->platform_meeting_account,

            'meeting_link_visible_before_minutes' => $meeting->meeting_link_visible_before_minutes,
            'meeting_link_visible_after_minutes' => $meeting->meeting_link_visible_after_minutes,
            'meeting_auto_close_enabled' => $meeting->meeting_auto_close_enabled,
            'student_join_url_visible' => $meeting->student_join_url_visible,
            'instructor_join_url_visible' => $meeting->instructor_join_url_visible,
            'participant_join_base_url' => $meeting->participant_join_base_url,

            'create_after_demo_booking_confirmation' => $meeting->create_after_demo_booking_confirmation,
            'create_after_paid_booking_confirmation' => $meeting->create_after_paid_booking_confirmation,

            'meeting_recording_enabled' => $meeting->recording_enabled,
            'effective_recording_availability' => app(RecordingAvailabilityResolver::class)->isAvailable() ? 'Available' : 'Unavailable',
            'recording_retention_days' => $meeting->recording_retention_days,
            'recording_student_playback_enabled' => $meeting->recording_student_playback_enabled,

            'google_meet_enabled' => $meeting->google_meet_enabled,
            'google_meet_recording_enabled' => $meeting->google_meet_recording_enabled,
            'google_meet_space_access' => GoogleMeetSpaceAccess::fromSetting($meeting->google_meet_space_access)->value,
            'google_auth_type' => $meeting->google_auth_type,
            'google_calendar_id' => $meeting->google_calendar_id,
            'recording_drive_root_folder_id' => $meeting->recording_drive_root_folder_id,
            'recording_drive_shared_drive_id' => $meeting->recording_drive_shared_drive_id,
            'google_credentials_json' => null,
            'google_credentials_configured' => $meeting->google_credentials_configured,
            'google_config_status' => $meeting->google_config_status,
            'google_last_checked_at' => $meeting->google_last_checked_at,
            'google_credentials_updated_at' => $meeting->google_credentials_updated_at,

            'zoom_enabled' => $meeting->zoom_enabled,
            'zoom_account_id' => $meeting->zoom_account_id,
            'zoom_client_id' => $meeting->zoom_client_id,
            'zoom_client_secret' => null,
            'zoom_host_user_id' => $meeting->zoom_host_user_id,
            'zoom_host_email' => $meeting->zoom_host_email,
            'zoom_default_timezone' => $meeting->zoom_default_timezone,
            'zoom_host_capacity_enabled' => $meeting->zoom_host_capacity_enabled,
            'zoom_host_capacity_buffer_minutes' => $meeting->zoom_host_capacity_buffer_minutes,
            'zoom_recording_enabled' => $meeting->zoom_recording_enabled,
            'zoom_recording_webhooks_enabled' => $meeting->zoom_recording_webhooks_enabled,
            'zoom_webhook_secret' => null,
            'zoom_recording_trash_source_after_persistence' => $meeting->zoom_recording_trash_source_after_persistence,
            'zoom_config_status' => $meeting->zoom_config_status,
            'zoom_last_checked_at' => $meeting->zoom_last_checked_at,
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
                            ->label('Save Meeting Settings')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                        Action::make('test_google_configuration')
                            ->label('Check Google Setup')
                            ->color('gray')
                            ->action('testGoogleConfiguration'),
                        Action::make('validate_zoom_configuration')
                            ->label('Check Zoom Setup')
                            ->color('gray')
                            ->action('validateZoomConfiguration'),
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
            $this->meetingsSection(),
            $this->joinLinksSection(),
            $this->meetingCreationSection(),
            $this->recordingSection(),
            $this->googleMeetSection(),
            $this->zoomSection(),
        ]);
    }

    // ── Sections ──────────────────────────────────────────────────────

    private function meetingsSection(): Section
    {
        return Section::make('Meetings')
            ->description('Turn lesson meetings on and choose the provider that creates them.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Toggle::make('meetings_enabled')
                        ->label('Enable Meetings')
                        ->helperText('Turn off to stop creating meeting links for any lesson.'),
                    Select::make('default_provider')
                        ->label('Default Provider')
                        ->options([
                            'manual' => 'Manual',
                            'google_meet' => 'Google Meet',
                            'zoom' => 'Zoom',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Used for automatically created meetings. It must be set up below.'),
                    Toggle::make('manual_provider_enabled')
                        ->label('Allow Manual Links')
                        ->helperText('Lets an admin paste a meeting link on a booking.'),
                    TextInput::make('platform_meeting_account')
                        ->label('Platform Meeting Account')
                        ->maxLength(255)
                        ->helperText('For reference only.'),
                ]),
            ]);
    }

    private function joinLinksSection(): Section
    {
        return Section::make('Join Links')
            ->description('When participants can join, and where the join link points.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    $this->integerInput('meeting_link_visible_before_minutes', 'Open Before Start (minutes)', 0, 10080)
                        ->helperText('How early the join link appears.'),
                    $this->integerInput('meeting_link_visible_after_minutes', 'Open After End (minutes)', 0, 10080)
                        ->helperText('How long the link stays available after the lesson ends.'),
                    Toggle::make('student_join_url_visible')
                        ->label('Students Can Join')
                        ->helperText('Off hides the join link from students entirely.'),
                    Toggle::make('instructor_join_url_visible')
                        ->label('Instructors Can Join')
                        ->helperText('Off hides the join link from instructors entirely.'),
                    Toggle::make('meeting_auto_close_enabled')
                        ->label('Close Meetings When the Window Ends')
                        ->helperText('Ends the provider meeting so a class or recording cannot run on. Google Meet only.'),
                    TextInput::make('participant_join_base_url')
                        ->label('Join Link Domain')
                        ->placeholder('https://meet.sirieducation.com')
                        ->maxLength(255)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && MeetingJoinHandoffService::normalizeOrigin((string) $value) === null) {
                                $fail('Enter an HTTPS address with only a host, for example https://meet.sirieducation.com.');
                            }
                        })
                        ->helperText('HTTPS address that join links use. Leave empty to use the main site.'),
                ]),
            ]);
    }

    private function meetingCreationSection(): Section
    {
        return Section::make('Automatic Creation')
            ->description('When a booking is confirmed, create its meeting without an admin.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Toggle::make('create_after_demo_booking_confirmation')
                        ->label('Free Demos')
                        ->helperText('Create the meeting as soon as a free demo is confirmed.'),
                    Toggle::make('create_after_paid_booking_confirmation')
                        ->label('Paid Lessons')
                        ->helperText('Create the meeting as soon as payment is confirmed. Off means an admin creates it.'),
                ]),
            ]);
    }

    private function recordingSection(): Section
    {
        return Section::make('Recording')
            ->description('Whether lessons are recorded, how long recordings are kept, and who can watch them.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Toggle::make('meeting_recording_enabled')
                        ->label('Record Lessons')
                        ->helperText('Also requires the Recording feature in Platform Foundation.'),
                    Placeholder::make('effective_recording_availability_display')
                        ->label('Recording Right Now')
                        ->content(fn (): string => ($this->data['effective_recording_availability'] ?? null) === 'Available'
                            ? 'Available — new lessons are recorded'
                            : 'Unavailable — new lessons are not recorded')
                        ->helperText('The result of both switches, as saved.'),
                    $this->integerInput('recording_retention_days', 'Keep Recordings For (days)', 0, 3650)
                        ->helperText('Recordings are removed after this many days.'),
                    Toggle::make('recording_student_playback_enabled')
                        ->label('Students Can Watch Recordings')
                        ->helperText('Off hides every recording from students. Single recordings can still be withheld from the Recordings screen.'),
                ]),
            ]);
    }

    private function googleMeetSection(): Section
    {
        return Section::make('Google Meet')
            ->description('Service account used to create Meet links and fetch recordings from Drive.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Toggle::make('google_meet_enabled')->label('Enable Google Meet'),
                    Toggle::make('google_meet_recording_enabled')
                        ->label('Fetch Meet Recordings')
                        ->helperText('Needs the Meet and Drive read scopes on the service account.'),
                    Select::make('google_meet_space_access')
                        ->label('Who Can Join a Meet')
                        ->options(GoogleMeetSpaceAccess::options())
                        ->required()
                        ->native(false)
                        ->helperText('Google records only while the host is present. "Host admits participants" keeps the whole lesson on the recording. Applies to new lessons.'),
                    Select::make('google_auth_type')
                        ->label('Authentication')
                        ->options([
                            'service_account' => 'Service Account',
                            'oauth_user' => 'OAuth User (coming later)',
                        ])
                        // The provider only treats a service account as
                        // configured; letting an admin pick OAuth would stop
                        // Meet creation without saying why.
                        ->disableOptionWhen(fn (string $value): bool => $value === 'oauth_user')
                        ->required()
                        ->native(false),
                    TextInput::make('google_calendar_id')
                        ->label('Calendar ID')
                        ->maxLength(255),
                    TextInput::make('recording_drive_root_folder_id')
                        ->label('Recordings Folder ID')
                        ->maxLength(255)
                        ->helperText('Drive folder that stores recording copies, from the folder URL. Required for recording.'),
                    TextInput::make('recording_drive_shared_drive_id')
                        ->label('Shared Drive ID')
                        ->maxLength(255)
                        ->helperText('Only if the folder is in a Shared Drive.'),
                    Placeholder::make('google_credentials_configured_display')
                        ->label('Credentials Stored')
                        ->content(fn (): string => $this->yesNo($this->data['google_credentials_configured'] ?? null)),
                    Placeholder::make('google_config_status_display')
                        ->label('Setup Status')
                        ->content(fn (): string => $this->configStatusLabel($this->data['google_config_status'] ?? null)),
                    Placeholder::make('google_last_checked_at_display')
                        ->label('Last Checked')
                        ->content(fn (): string => $this->timestampLabel($this->data['google_last_checked_at'] ?? null)),
                    Placeholder::make('google_credentials_updated_at_display')
                        ->label('Credentials Last Replaced')
                        ->content(fn (): string => $this->timestampLabel($this->data['google_credentials_updated_at'] ?? null)),
                ]),
                Textarea::make('google_credentials_json')
                    ->label('Service Account JSON')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Paste a new key to replace the stored one. Leave blank to keep it.'),
            ]);
    }

    private function zoomSection(): Section
    {
        return Section::make('Zoom')
            ->description('Server-to-Server OAuth app for the platform\'s Zoom host account.')
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    Toggle::make('zoom_enabled')->label('Enable Zoom'),
                    TextInput::make('zoom_default_timezone')
                        ->label('Default Timezone')
                        ->maxLength(64)
                        ->placeholder('Asia/Kolkata'),
                    TextInput::make('zoom_account_id')->label('Account ID')->maxLength(255),
                    TextInput::make('zoom_client_id')->label('Client ID')->maxLength(255),
                    TextInput::make('zoom_client_secret')
                        ->label('Client Secret')
                        ->password()
                        ->maxLength(255)
                        ->helperText('Leave blank to keep the stored secret.'),
                    TextInput::make('zoom_webhook_secret')
                        ->label('Webhook Secret Token')
                        ->password()
                        ->maxLength(255)
                        ->helperText('From the Zoom app\'s Event Subscriptions page. Leave blank to keep it.'),
                    TextInput::make('zoom_host_user_id')
                        ->label('Host User ID')
                        ->maxLength(255)
                        ->helperText('Zoom user that meetings are scheduled under. Takes precedence over Host Email.'),
                    TextInput::make('zoom_host_email')
                        ->label('Host Email')
                        ->email()
                        ->maxLength(255),
                    Toggle::make('zoom_host_capacity_enabled')
                        ->label('Reserve Host Capacity')
                        ->helperText('Each Zoom booking reserves the host for its lesson window and is refused when the host is taken. Register the host and run the preflight first.'),
                    $this->integerInput('zoom_host_capacity_buffer_minutes', 'Host Buffer (minutes)', 0, 120)
                        ->helperText('Extra time kept free on the host before and after each lesson.'),
                    Toggle::make('zoom_recording_enabled')
                        ->label('Fetch Zoom Recordings')
                        ->helperText('Needs a licensed Zoom account with cloud recording.'),
                    Toggle::make('zoom_recording_webhooks_enabled')
                        ->label('Accept Recording Webhooks')
                        ->helperText('Zoom notifies SIRI when a recording is ready. Off relies on the scheduled check.'),
                    Toggle::make('zoom_recording_trash_source_after_persistence')
                        ->label('Trash Zoom Copy After Verification')
                        ->columnSpanFull()
                        ->helperText('Moves Zoom\'s copy to its recoverable trash once SIRI has stored and verified its own. Off keeps both copies.'),
                    Placeholder::make('zoom_config_status_display')
                        ->label('Setup Status')
                        ->content(fn (): string => $this->configStatusLabel($this->data['zoom_config_status'] ?? null)),
                    Placeholder::make('zoom_last_checked_at_display')
                        ->label('Last Checked')
                        ->content(fn (): string => $this->timestampLabel($this->data['zoom_last_checked_at'] ?? null)),
                ]),
            ]);
    }

    // ── Read-only labels ──────────────────────────────────────────────

    private function yesNo(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }

    private function configStatusLabel(mixed $status): string
    {
        return match ((string) $status) {
            'ready' => 'Ready',
            'incomplete' => 'Incomplete — some required details are missing',
            'invalid' => 'Invalid — the stored credentials were rejected',
            'not_configured' => 'Not configured',
            '' => 'Not checked yet',
            default => (string) $status,
        };
    }

    private function timestampLabel(mixed $value): string
    {
        if (blank($value)) {
            return 'Never';
        }

        try {
            $moment = Carbon::parse((string) $value);
        } catch (Throwable) {
            return (string) $value; // an unparseable value is still worth showing
        }

        return $moment->timezone(config('app.timezone'))->format('j M Y, H:i').' ('.$moment->diffForHumans().')';
    }

    // ── Save ──────────────────────────────────────────────────────────

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (Halt) {
            return;
        }

        if ($this->refuseUnsafeZoomCapacity($data)) {
            return;
        }

        // A new Google key is validated before anything is written, so a
        // bad key never reaches the database or blocks the rest of the save.
        $newGoogleCredentials = null;

        if (filled($data['google_credentials_json'] ?? null)) {
            try {
                $newGoogleCredentials = $this->validateGoogleCredentialsJson((string) $data['google_credentials_json']);
            } catch (InvalidArgumentException $e) {
                Notification::make()->title('Google credentials not saved')->body($e->getMessage())->danger()->send();

                return;
            }
        }

        $saved = $this->saveSettingsWithAudit(MeetingSettings::class, 'settings', function (MeetingSettings $settings) use ($data, $newGoogleCredentials): void {
            $this->applyMeetingSettings($settings, $data);
            $this->applyGoogleSettings($settings, $data, $newGoogleCredentials !== null);
            $this->applyZoomSettings($settings, $data);
        });

        if (! $saved) {
            return;
        }

        $this->mount();

        Notification::make()
            ->title('Meeting settings saved')
            ->body($newGoogleCredentials !== null
                ? sprintf('Google credentials replaced — client_email: %s, client_id: %s', $newGoogleCredentials['client_email'], $newGoogleCredentials['client_id'])
                : null)
            ->success()
            ->send();
    }

    /**
     * Two gates the stored state must never violate, refused here with
     * the safe order of operations rather than left to fail at booking:
     * Zoom cannot be the default while host reservation is off (every
     * Zoom booking would be refused), and reservation cannot be switched
     * on while the preflight has findings. Returns true when the save
     * was refused.
     *
     * @param  array<string, mixed>  $data
     */
    private function refuseUnsafeZoomCapacity(array $data): bool
    {
        $capacityOn = $this->bool($data, 'zoom_host_capacity_enabled');

        if (($data['default_provider'] ?? null) === ZoomMeetingProvider::KEY && ! $capacityOn) {
            Notification::make()
                ->title('Meeting settings not saved')
                ->body('Zoom cannot be the default provider while "Reserve Host Capacity" is off — every Zoom booking would be refused. Register the host, run meetings:zoom-hosts:preflight, turn reservation on, then choose Zoom. To roll back, choose Google Meet first, then turn reservation off.')
                ->danger()
                ->send();

            return true;
        }

        if ($capacityOn && ! app(MeetingSettings::class)->zoom_host_capacity_enabled) {
            $report = app(ZoomHostCapacityPreflightService::class)->report();

            if ($report->hasFindings()) {
                Notification::make()
                    ->title('Zoom host capacity reservation not enabled')
                    ->body($report->summary().' Run meetings:zoom-hosts:preflight for the full list; nothing on this page was saved.')
                    ->danger()
                    ->send();

                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $data */
    private function applyMeetingSettings(MeetingSettings $settings, array $data): void
    {
        $settings->meetings_enabled = $this->bool($data, 'meetings_enabled');
        $settings->default_provider = $data['default_provider'];
        $settings->manual_provider_enabled = $this->bool($data, 'manual_provider_enabled');
        $settings->platform_meeting_account = $this->nullableString($data, 'platform_meeting_account');

        $settings->meeting_link_visible_before_minutes = (int) $data['meeting_link_visible_before_minutes'];
        $settings->meeting_link_visible_after_minutes = (int) $data['meeting_link_visible_after_minutes'];
        $settings->meeting_auto_close_enabled = $this->bool($data, 'meeting_auto_close_enabled');
        $settings->student_join_url_visible = $this->bool($data, 'student_join_url_visible');
        $settings->instructor_join_url_visible = $this->bool($data, 'instructor_join_url_visible');
        $settings->participant_join_base_url = MeetingJoinHandoffService::normalizeOrigin($data['participant_join_base_url'] ?? null);

        $settings->create_after_demo_booking_confirmation = $this->bool($data, 'create_after_demo_booking_confirmation');
        $settings->create_after_paid_booking_confirmation = $this->bool($data, 'create_after_paid_booking_confirmation');

        $settings->recording_enabled = $this->bool($data, 'meeting_recording_enabled');
        $settings->recording_retention_days = (int) $data['recording_retention_days'];
        $settings->recording_student_playback_enabled = $this->bool($data, 'recording_student_playback_enabled');
    }

    /** @param  array<string, mixed>  $data */
    private function applyGoogleSettings(MeetingSettings $settings, array $data, bool $replaceCredentials): void
    {
        $settings->google_meet_enabled = $this->bool($data, 'google_meet_enabled');
        $settings->google_meet_recording_enabled = $this->bool($data, 'google_meet_recording_enabled');
        $settings->google_meet_space_access = GoogleMeetSpaceAccess::fromSetting($data['google_meet_space_access'] ?? null)->value;
        $settings->google_auth_type = $data['google_auth_type'];
        $settings->google_calendar_id = $this->nullableString($data, 'google_calendar_id');
        $settings->recording_drive_root_folder_id = $this->nullableString($data, 'recording_drive_root_folder_id');
        $settings->recording_drive_shared_drive_id = $this->nullableString($data, 'recording_drive_shared_drive_id');

        // A blank submit keeps the existing encrypted key.
        if ($replaceCredentials) {
            $settings->google_credentials_json = Crypt::encryptString((string) $data['google_credentials_json']);
            $settings->google_credentials_updated_at = Carbon::now()->toIso8601String();
        }
    }

    /** @param  array<string, mixed>  $data */
    private function applyZoomSettings(MeetingSettings $settings, array $data): void
    {
        $enabled = $this->bool($data, 'zoom_enabled');

        $settings->zoom_enabled = $enabled;
        $settings->zoom_account_id = $this->nullableString($data, 'zoom_account_id');
        $settings->zoom_client_id = $this->nullableString($data, 'zoom_client_id');
        $settings->zoom_host_user_id = $this->nullableString($data, 'zoom_host_user_id');
        $settings->zoom_host_email = $this->nullableString($data, 'zoom_host_email');
        $settings->zoom_default_timezone = $this->nullableString($data, 'zoom_default_timezone');
        $settings->zoom_host_capacity_enabled = $this->bool($data, 'zoom_host_capacity_enabled');
        $settings->zoom_host_capacity_buffer_minutes = (int) ($data['zoom_host_capacity_buffer_minutes'] ?? 5);

        // A provider that cannot create meetings cannot record them: the
        // recording switches are only ever stored on alongside Zoom itself.
        $settings->zoom_recording_enabled = $enabled && $this->bool($data, 'zoom_recording_enabled');
        $settings->zoom_recording_webhooks_enabled = $settings->zoom_recording_enabled && $this->bool($data, 'zoom_recording_webhooks_enabled');
        $settings->zoom_recording_trash_source_after_persistence = $settings->zoom_recording_enabled && $this->bool($data, 'zoom_recording_trash_source_after_persistence');

        // Blank secret fields keep the stored values.
        foreach (['zoom_client_secret', 'zoom_webhook_secret'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $settings->{$secret} = Crypt::encryptString((string) $data[$secret]);
            }
        }
    }

    /**
     * Fails closed on anything that is not a well-formed service-account
     * JSON — never partially trusts it, never logs it.
     *
     * @return array{client_id: string, client_email: string}
     *
     * @throws InvalidArgumentException with a message safe to show the admin
     */
    private function validateGoogleCredentialsJson(string $json): array
    {
        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The service account JSON is not valid JSON.');
        }

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account') {
            throw new InvalidArgumentException('The service account JSON must have "type": "service_account".');
        }

        foreach (['client_id', 'client_email', 'private_key'] as $field) {
            if (blank($decoded[$field] ?? null)) {
                throw new InvalidArgumentException(sprintf('The service account JSON is missing "%s".', $field));
            }
        }

        return [
            'client_id' => (string) $decoded['client_id'],
            'client_email' => (string) $decoded['client_email'],
        ];
    }

    // ── Provider checks ───────────────────────────────────────────────

    public function testGoogleConfiguration(): void
    {
        $service = app(GoogleCalendarConfigurationService::class);
        $status = $service->check();
        $diagnostics = $service->lastDiagnostics();
        $reason = $service->lastDiagnostic();

        $this->mount();

        $lines = $diagnostics === null ? [] : [
            sprintf('Client ID: %s', $diagnostics->clientId ?? 'unknown'),
            sprintf('Client email: %s', $diagnostics->clientEmail ?? 'unknown'),
            sprintf('Delegated subject: %s', $diagnostics->delegatedSubject ?? 'unknown'),
            sprintf('Requested scopes: %s', implode(', ', $diagnostics->requestedScopes)),
            sprintf('Calendar ID: %s', $diagnostics->calendarId ?? 'unknown'),
            sprintf('Token acquired: %s', $diagnostics->tokenAcquired ? 'yes' : 'no'),
            sprintf('Allowed conference types: [%s]', implode(', ', $diagnostics->allowedConferenceTypes) ?: 'none'),
        ];

        $this->notifyCheck('Google configuration', $status, $lines, $reason);
    }

    public function validateZoomConfiguration(): void
    {
        $service = app(ZoomConfigurationService::class);
        $status = $service->check();
        $diagnostics = $service->lastDiagnostics();
        $reason = $service->lastDiagnostic();

        $this->mount();

        $lines = $diagnostics === null ? [] : [
            sprintf('Account ID: %s', $diagnostics->accountId ?? 'unknown'),
            sprintf('Client ID: %s', $diagnostics->clientId ?? 'unknown'),
            sprintf('Host user: %s', $diagnostics->hostUser ?? 'unknown'),
            sprintf('Token acquired: %s', $diagnostics->tokenAcquired ? 'yes' : 'no'),
            sprintf('Meeting creation verified: %s', $diagnostics->meetingCreationVerified ? 'yes' : 'no'),
        ];

        $this->notifyCheck('Zoom configuration', $status, $lines, $reason);
    }

    /** @param  list<string>  $lines */
    private function notifyCheck(string $subject, string $status, array $lines, ?string $reason): void
    {
        if ($reason !== null) {
            $lines[] = sprintf('Reason: %s', $reason);
        }

        Notification::make()
            ->title($subject.': '.$status)
            ->body($lines === [] ? null : implode("\n", $lines))
            ->{$status === 'ready' ? 'success' : 'warning'}()
            ->send();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function integerInput(string $name, string $label, int $min, int $max): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->integer()
            ->minValue($min)
            ->maxValue($max)
            ->required();
    }

    /** @param  array<string, mixed>  $data */
    private function bool(array $data, string $key): bool
    {
        return (bool) ($data[$key] ?? false);
    }

    /** @param  array<string, mixed>  $data */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return filled($value) ? trim((string) $value) : null;
    }
}
