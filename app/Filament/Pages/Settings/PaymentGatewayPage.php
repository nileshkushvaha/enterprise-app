<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Filament\Navigation\Concerns\HasCentralizedNavigation;
use App\Filament\Navigation\Concerns\HasSettingsSectionBreadcrumb;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PaymentGatewayPage extends PaymentSettingsPage
{
    use HasCentralizedNavigation;
    use HasSettingsSectionBreadcrumb;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'payment-settings/gateways';

    public static function getLabel(): string
    {
        return 'Payment Gateways';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Payment Gateways';
    }

    public function getSubheading(): ?string
    {
        return 'Which gateway collects payments, its credentials and webhook secrets. Leaving a secret blank keeps the stored value.';
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
                        Action::make('validate_credentials')
                            ->label('Check credentials')
                            ->tooltip('Checks that the saved keys are present and have the expected format, and records the result on the tab. Does not contact the gateway.')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->color('gray')
                            ->form([
                                Select::make('gateway')
                                    ->label('Gateway')
                                    ->options($this->gatewayOptions())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(fn (array $data) => $this->validateGatewayCredentials($data['gateway'])),
                        Action::make('test_connection')
                            ->label('Check enabled gateway')
                            ->tooltip('Same format check, for a gateway that is switched on. No request is sent to the gateway; a real payment is the only end-to-end test.')
                            ->icon(Heroicon::OutlinedSignal)
                            ->color('gray')
                            ->form([
                                Select::make('gateway')
                                    ->label('Gateway')
                                    ->options($this->gatewayOptions())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(fn (array $data) => $this->testGatewayConnection($data['gateway'])),
                        Action::make('generate_webhook_secret')
                            ->label('Generate test webhook secret')
                            ->tooltip('Fills a random secret into the form for local testing only. Production uses the secret shown in the gateway dashboard.')
                            ->icon(Heroicon::OutlinedKey)
                            ->color('gray')
                            ->form([
                                Select::make('gateway')
                                    ->label('Gateway')
                                    ->options($this->gatewayOptions())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(fn (array $data) => $this->generateWebhookSecret($data['gateway'])),
                        Action::make('copy_webhook_url')
                            ->label('Copy webhook URL')
                            ->icon(Heroicon::OutlinedClipboardDocument)
                            ->color('gray')
                            ->form([
                                Select::make('gateway')
                                    ->label('Gateway')
                                    ->options($this->gatewayOptions())
                                    ->required()
                                    ->native(false),
                            ])
                            ->action(fn (array $data) => $this->copyWebhookUrl($data['gateway'])),
                        Action::make('reset_credentials')
                            ->label('Remove all stored secrets')
                            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Remove every stored secret?')
                            ->modalDescription('Deletes the stored secret keys and webhook secrets of every gateway. Key IDs and publishable keys stay. Online payments stop until new secrets are saved. This cannot be undone.')
                            ->modalSubmitActionLabel('Remove secrets')
                            ->action(fn () => $this->resetGatewayCredentials()),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->gatewaySchema());
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (Halt) {
            return;
        }

        if (! $this->saveGatewaySettings($data)) {
            return;
        }

        Notification::make()
            ->title('Payment gateways saved')
            ->success()
            ->send();
    }
}
