<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Security\PasswordRuleBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;

class AdminChangePassword extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $slug = 'change-password';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getLabel(): string
    {
        return 'Change Password';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Change Password';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Update your account password. Use a strong, unique password.';
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'Dashboard',
            '#' => 'Change Password',
        ];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    // ── Wire the form into the page content ──────────────────────────
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            FormComponent::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    ActionsComponent::make([
                        Action::make('save')
                            ->label('Update Password')
                            ->submit('save')
                            ->keyBindings(['mod+s']),

                        Action::make('cancel')
                            ->label('Cancel')
                            ->url(fn () => filament()->getUrl())
                            ->color('gray'),
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
        return $schema
            ->components([
                Section::make('Update Password')
                    ->description('Enter your current password, then choose a new one.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->autocomplete('current-password'),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->rule(app(PasswordRuleBuilder::class)->build())
                            ->showAllValidationMessages()
                            ->autocomplete('new-password')
                            ->same('password_confirmation'),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->dehydrated(false)
                            ->autocomplete('new-password'),
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

        $user = Filament::auth()->user();

        $user->update([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
        ]);

        // Re-hash session so user stays logged in after password change
        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->data = [];
        $this->form->fill();

        Notification::make()
            ->title('Password updated successfully')
            ->success()
            ->send();
    }
}
