<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Navigation\Concerns\HasCentralizedNavigation;
use App\Filament\Navigation\Concerns\HasSettingsSectionBreadcrumb;
use App\Models\User;
use App\Settings\ReviewSettings;
use BackedEnum;
use Filament\Actions\Action;
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
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The sole runtime-configuration surface for ReviewSettings (Phase
 * 17U.2 §§2-6). Exposes only the already-implemented Version 1
 * settings whitelist — deliberately no instructor-response, AI/
 * sentiment, marketplace-ranking, Learning-Plan-linkage, or
 * notification-template-editing fields, since none of those exist in
 * ReviewSettings or anywhere else yet.
 *
 * `reviews.reviews_enabled` (the "General" section's first toggle) is
 * the ONE canonical reviews on/off switch platform-wide — see
 * ReviewSettings::$reviews_enabled and FeatureSettings' docblock. This
 * page is the only place it can be changed; disabling it here blocks
 * new eligibility, submissions, edits, reports, and the "ready to
 * review" notification everywhere else in the app (already enforced
 * at the service/action layer), while every historical review,
 * moderation record, report, aggregate, and audit entry stays exactly
 * as it was.
 *
 * View/mutate are two distinct permissions (`settings.reviews_quality.view`
 * / `.update`) — access to see the page does not imply
 * the ability to save it; save() re-checks the update permission at
 * execution time, not just via a hidden button.
 */
class ReviewQualitySettingsPage extends Page
{
    use HasCentralizedNavigation;
    use HasSettingsSectionBreadcrumb;
    use LogsSettingsUpdates;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Reviews & Quality';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'settings/reviews-quality';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getLabel(): string
    {
        return 'Reviews & Quality';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Reviews & Quality';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Whether students can review lessons, the rating scale, moderation, quality alerts and how notifications are sent.';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return self::hasPermission($user, 'settings.reviews_quality.view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $settings = app(ReviewSettings::class);

        $this->form->fill([
            'reviews_enabled' => $settings->reviews_enabled,
            'paid_lesson_reviews_enabled' => $settings->paid_lesson_reviews_enabled,
            'demo_review_policy' => $settings->demo_review_policy,
            'review_window_days' => $settings->review_window_days,
            'change_reason' => null,

            'rating_min' => $settings->rating_min,
            'rating_max' => $settings->rating_max,
            'written_review_required' => $settings->written_review_required,
            'review_min_length' => $settings->review_min_length,
            'review_max_length' => $settings->review_max_length,
            'rating_dimensions_enabled' => $settings->rating_dimensions_enabled,
            'review_max_tags' => $settings->review_max_tags,

            'moderation_model' => $settings->moderation_model,
            'auto_publish_clean_reviews' => $settings->auto_publish_clean_reviews,
            'public_review_identity_mode' => $settings->public_review_identity_mode,
            'review_reporting_enabled' => $settings->review_reporting_enabled,
            'review_editing_enabled' => $settings->review_editing_enabled,
            'review_edit_window_hours' => $settings->review_edit_window_hours,

            'quality_alerts_enabled' => $settings->quality_alerts_enabled,
            'low_rating_threshold' => $settings->low_rating_threshold,
            'single_low_rating_alert_enabled' => $settings->single_low_rating_alert_enabled,
            'repeated_low_rating_count' => $settings->repeated_low_rating_count,
            'repeated_low_rating_window_days' => $settings->repeated_low_rating_window_days,
            'repeated_no_show_count' => $settings->repeated_no_show_count,
            'repeated_no_show_window_days' => $settings->repeated_no_show_window_days,
            'repeated_cancellation_count' => $settings->repeated_cancellation_count,
            'repeated_cancellation_window_days' => $settings->repeated_cancellation_window_days,

            'quality_dashboard_low_rating_threshold' => $settings->quality_dashboard_low_rating_threshold,
            'quality_dashboard_high_rating_threshold' => $settings->quality_dashboard_high_rating_threshold,
            'quality_dashboard_min_review_count' => $settings->quality_dashboard_min_review_count,

            'review_channel_email_enabled' => $settings->review_channel_email_enabled,
            'review_channel_whatsapp_enabled' => $settings->review_channel_whatsapp_enabled,
            'review_channel_sms_enabled' => $settings->review_channel_sms_enabled,
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
            Section::make('Reviews')
                ->description('Who may leave a review and for how long after a lesson.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('reviews_enabled')
                            ->label('Enable reviews')
                            ->helperText('Off stops new reviews, edits and reports everywhere. Existing reviews stay visible.'),
                        Toggle::make('paid_lesson_reviews_enabled')
                            ->label('Allow reviews of paid lessons'),
                        Select::make('demo_review_policy')
                            ->label('Demo lesson reviews')
                            ->options([
                                'disabled' => 'Not allowed',
                                'private_only' => 'Private feedback to the instructor only',
                                'public' => 'Public, like paid lessons',
                            ])
                            ->required()
                            ->native(false),
                        $this->integerInput('review_window_days', 'Review window (days)', 1, 3650)
                            ->helperText('Days after a completed lesson during which a review can be left.'),
                    ]),
                    Textarea::make('change_reason')
                        ->label('Reason for change')
                        ->rows(2)
                        ->maxLength(1000)
                        ->helperText('Required when turning reviews on or off or changing the moderation model. Kept in the audit trail.')
                        ->columnSpanFull(),
                ]),

            Section::make('Rating & written feedback')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        $this->integerInput('rating_min', 'Lowest rating', 0, 100),
                        $this->integerInput('rating_max', 'Highest rating', 0, 100)
                            ->helperText('Changing the scale never rewrites past reviews.'),
                        $this->integerInput('review_max_tags', 'Tags per review (max)', 0, 50),
                        Toggle::make('written_review_required')
                            ->label('Require written feedback'),
                        $this->integerInput('review_min_length', 'Written feedback minimum (characters)', 0, 10000),
                        $this->integerInput('review_max_length', 'Written feedback maximum (characters)', 0, 10000),
                    ]),
                    Toggle::make('rating_dimensions_enabled')
                        ->label('Allow ratings per dimension')
                        ->helperText('The optional per-aspect ratings alongside the overall score.'),
                ]),

            Section::make('Moderation & privacy')
                ->description('What happens to a new review, what students can do afterwards, and how they are named publicly.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('moderation_model')
                            ->label('Moderation')
                            ->options([
                                'pre_moderation' => 'Every review waits for an administrator',
                                'post_moderation' => 'Clean reviews publish, administrators review afterwards',
                                'risk_based' => 'Clean reviews publish, flagged ones wait',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Reviews with unsafe content always wait for an administrator.'),
                        Toggle::make('auto_publish_clean_reviews')
                            ->label('Auto-publish clean reviews')
                            ->helperText('Off means nothing publishes automatically, whatever the moderation model.'),
                        Select::make('public_review_identity_mode')
                            ->label('Reviewer shown publicly as')
                            ->options([
                                'anonymous' => 'Verified student (anonymous)',
                                'first_name_initial' => 'First name initial',
                                'first_name_only' => 'First name',
                            ])
                            ->required()
                            ->native(false),
                        Toggle::make('review_reporting_enabled')
                            ->label('Allow reporting a review'),
                        Toggle::make('review_editing_enabled')
                            ->label('Allow editing a review'),
                        $this->integerInput('review_edit_window_hours', 'Edit window (hours)', 1, 8760)
                            ->helperText('Applies while editing is allowed.'),
                    ]),
                ]),

            Section::make('Quality alerts')
                ->description('Raise an operational alert when a pattern appears. Off means no detector runs, whatever the thresholds.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('quality_alerts_enabled')
                            ->label('Enable quality alerts'),
                        Toggle::make('single_low_rating_alert_enabled')
                            ->label('Alert on every single low rating'),
                    ]),
                    Grid::make(3)->schema([
                        $this->integerInput('low_rating_threshold', 'Low rating is at or below', 0, 100)
                            ->helperText('On the rating scale above.'),
                        $this->integerInput('repeated_low_rating_count', 'Repeated low ratings: count', 1, 1000),
                        $this->integerInput('repeated_low_rating_window_days', 'Repeated low ratings: within (days)', 1, 3650),
                        $this->integerInput('repeated_no_show_count', 'Repeated no-shows: count', 1, 1000),
                        $this->integerInput('repeated_no_show_window_days', 'Repeated no-shows: within (days)', 1, 3650),
                        $this->integerInput('repeated_cancellation_count', 'Repeated cancellations: count', 1, 1000),
                        $this->integerInput('repeated_cancellation_window_days', 'Repeated cancellations: within (days)', 1, 3650),
                    ]),
                ]),

            Section::make('Notification channels')
                ->description('For review and quality-alert notifications. In-app notifications are always sent.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('review_channel_email_enabled')->label('Email'),
                        Toggle::make('review_channel_whatsapp_enabled')
                            ->label('WhatsApp')
                            ->helperText('No WhatsApp provider is set up: messages are logged, not sent.'),
                        Toggle::make('review_channel_sms_enabled')
                            ->label('SMS')
                            ->helperText('No SMS provider is set up: messages are logged, not sent.'),
                    ]),
                ]),

            Section::make('Dashboard classification')
                ->description('How the admin quality dashboard labels instructors. Display only; never triggers an alert.')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(3)->schema([
                        $this->numericInput('quality_dashboard_low_rating_threshold', 'Low-rated at or below', 0),
                        $this->numericInput('quality_dashboard_high_rating_threshold', 'Highly rated at or above', 0),
                        $this->integerInput('quality_dashboard_min_review_count', 'Reviews needed before classifying', 1, 100000),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        if (! $this->canUpdate()) {
            Notification::make()
                ->title('You do not have permission to update these settings.')
                ->danger()
                ->send();

            return;
        }

        try {
            $data = $this->form->getState();
        } catch (Halt) {
            Notification::make()
                ->title('Reviews & Quality settings were not saved')
                ->body('Please correct the highlighted fields.')
                ->danger()
                ->send();

            return;
        }

        $settings = app(ReviewSettings::class);
        $before = $this->snapshotSettings($settings);

        $errors = $this->validateBusinessRules($data);

        if ($errors !== []) {
            Notification::make()
                ->title('Reviews & Quality settings were not saved')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();

            return;
        }

        $reasonRequired = $data['reviews_enabled'] !== $before['reviews_enabled']
            || $data['moderation_model'] !== $before['moderation_model'];
        $reason = filled($data['change_reason'] ?? null) ? trim((string) $data['change_reason']) : null;

        if ($reasonRequired && $reason === null) {
            Notification::make()
                ->title('Reviews & Quality settings were not saved')
                ->body('A reason is required when changing the Reviews Enabled switch or the Moderation Model.')
                ->danger()
                ->send();

            return;
        }

        $saved = $this->saveSettingsWithAudit(ReviewSettings::class, 'reviews', function (ReviewSettings $settings) use ($data): void {
            $settings->reviews_enabled = (bool) $data['reviews_enabled'];
            $settings->paid_lesson_reviews_enabled = (bool) $data['paid_lesson_reviews_enabled'];
            $settings->demo_review_policy = $data['demo_review_policy'];
            $settings->review_window_days = (int) $data['review_window_days'];

            $settings->rating_min = (int) $data['rating_min'];
            $settings->rating_max = (int) $data['rating_max'];
            $settings->written_review_required = (bool) $data['written_review_required'];
            $settings->review_min_length = (int) $data['review_min_length'];
            $settings->review_max_length = (int) $data['review_max_length'];
            $settings->rating_dimensions_enabled = (bool) $data['rating_dimensions_enabled'];
            $settings->review_max_tags = (int) $data['review_max_tags'];

            $settings->moderation_model = $data['moderation_model'];
            $settings->auto_publish_clean_reviews = (bool) $data['auto_publish_clean_reviews'];
            $settings->public_review_identity_mode = $data['public_review_identity_mode'];
            $settings->review_reporting_enabled = (bool) $data['review_reporting_enabled'];
            $settings->review_editing_enabled = (bool) $data['review_editing_enabled'];
            $settings->review_edit_window_hours = (int) $data['review_edit_window_hours'];

            $settings->quality_alerts_enabled = (bool) $data['quality_alerts_enabled'];
            $settings->low_rating_threshold = (int) $data['low_rating_threshold'];
            $settings->single_low_rating_alert_enabled = (bool) $data['single_low_rating_alert_enabled'];
            $settings->repeated_low_rating_count = (int) $data['repeated_low_rating_count'];
            $settings->repeated_low_rating_window_days = (int) $data['repeated_low_rating_window_days'];
            $settings->repeated_no_show_count = (int) $data['repeated_no_show_count'];
            $settings->repeated_no_show_window_days = (int) $data['repeated_no_show_window_days'];
            $settings->repeated_cancellation_count = (int) $data['repeated_cancellation_count'];
            $settings->repeated_cancellation_window_days = (int) $data['repeated_cancellation_window_days'];

            $settings->quality_dashboard_low_rating_threshold = (float) $data['quality_dashboard_low_rating_threshold'];
            $settings->quality_dashboard_high_rating_threshold = (float) $data['quality_dashboard_high_rating_threshold'];
            $settings->quality_dashboard_min_review_count = (int) $data['quality_dashboard_min_review_count'];

            $settings->review_channel_email_enabled = (bool) $data['review_channel_email_enabled'];
            $settings->review_channel_whatsapp_enabled = (bool) $data['review_channel_whatsapp_enabled'];
            $settings->review_channel_sms_enabled = (bool) $data['review_channel_sms_enabled'];
        }, $reason !== null ? ['reason' => $reason] : []);

        if (! $saved) {
            return;
        }

        $this->mount();

        $warnings = $this->stubProviderWarnings($data, $before);

        Notification::make()
            ->title('Reviews & Quality settings saved')
            ->body($warnings === [] ? null : implode("\n", $warnings))
            ->{$warnings === [] ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateBusinessRules(array $data): array
    {
        $errors = [];

        $ratingMin = (int) $data['rating_min'];
        $ratingMax = (int) $data['rating_max'];

        if ($ratingMin >= $ratingMax) {
            $errors[] = 'Rating Min must be less than Rating Max.';
        }

        $minLength = (int) $data['review_min_length'];
        $maxLength = (int) $data['review_max_length'];

        if ($minLength > $maxLength) {
            $errors[] = 'Min Written Length must not be greater than Max Written Length.';
        }

        if (! in_array($data['demo_review_policy'], ['disabled', 'private_only', 'public'], true)) {
            $errors[] = 'Demo Review Policy must be one of: disabled, private_only, public.';
        }

        if (! in_array($data['moderation_model'], ['pre_moderation', 'post_moderation', 'risk_based'], true)) {
            $errors[] = 'Moderation Model must be one of: pre_moderation, post_moderation, risk_based.';
        }

        if (! in_array($data['public_review_identity_mode'], ['anonymous', 'first_name_initial', 'first_name_only'], true)) {
            $errors[] = 'Public Reviewer Identity must be one of: anonymous, first_name_initial, first_name_only.';
        }

        $lowRatingThreshold = (int) $data['low_rating_threshold'];

        if ($lowRatingThreshold < $ratingMin || $lowRatingThreshold > $ratingMax) {
            $errors[] = 'Low Rating Threshold must fall within the Rating Min/Max scale.';
        }

        $dashboardLow = (float) $data['quality_dashboard_low_rating_threshold'];
        $dashboardHigh = (float) $data['quality_dashboard_high_rating_threshold'];

        if ($dashboardLow >= $dashboardHigh) {
            $errors[] = 'Dashboard Low-rated Threshold must be less than the Highly-rated Threshold.';
        }

        if ($dashboardLow < $ratingMin || $dashboardHigh > $ratingMax) {
            $errors[] = 'Dashboard thresholds must fall within the Rating Min/Max scale.';
        }

        foreach ([
            'repeated_low_rating_count' => 'Repeated Low Rating Count',
            'repeated_no_show_count' => 'Repeated No-show Count',
            'repeated_cancellation_count' => 'Repeated Cancellation Count',
            'quality_dashboard_min_review_count' => 'Dashboard Minimum Review Count',
        ] as $field => $label) {
            if ((int) $data[$field] < 1) {
                $errors[] = sprintf('%s must be at least 1.', $label);
            }
        }

        foreach ([
            'review_window_days' => 'Review Window',
            'review_edit_window_hours' => 'Edit Window',
            'repeated_low_rating_window_days' => 'Repeated Low Rating Window',
            'repeated_no_show_window_days' => 'Repeated No-show Window',
            'repeated_cancellation_window_days' => 'Repeated Cancellation Window',
        ] as $field => $label) {
            if ((int) $data[$field] < 1) {
                $errors[] = sprintf('%s must be at least 1.', $label);
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $before @return list<string> */
    private function stubProviderWarnings(array $data, array $before): array
    {
        $warnings = [];

        if (($data['review_channel_whatsapp_enabled'] ?? false) && ! ($before['review_channel_whatsapp_enabled'] ?? false)) {
            $warnings[] = 'WhatsApp is enabled but no gateway is configured — messages will be logged and skipped, not delivered.';
        }

        if (($data['review_channel_sms_enabled'] ?? false) && ! ($before['review_channel_sms_enabled'] ?? false)) {
            $warnings[] = 'SMS is enabled but no gateway is configured — messages will be logged and skipped, not delivered.';
        }

        return $warnings;
    }

    private function canUpdate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return self::hasPermission($user, 'settings.reviews_quality.update');
    }

    private static function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function integerInput(string $name, string $label, int $min, int $max): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->integer()
            ->minValue($min)
            ->maxValue($max)
            ->required();
    }

    private function numericInput(string $name, string $label, int|float $min): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue($min)
            ->required();
    }
}
