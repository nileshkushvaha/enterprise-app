<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Services\Security\PasswordRuleBuilder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isCreate = $schema->getLivewire() instanceof CreateRecord;

        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([

                        Tab::make('Account')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('General Information')
                                    ->description('Basic account details for this user.')
                                    ->icon('heroicon-o-information-circle')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('name')
                                                ->label('Full Name')
                                                ->required()
                                                ->maxLength(255)
                                                ->placeholder('John Doe'),

                                            TextInput::make('email')
                                                ->label('Email Address')
                                                ->email()
                                                ->required()
                                                ->maxLength(255)
                                                ->unique(
                                                    table: 'users',
                                                    column: 'email',
                                                    ignoreRecord: true,
                                                )
                                                ->placeholder('john@example.com'),
                                        ]),

                                        Grid::make(2)->schema([
                                            TextInput::make('password')
                                                ->label('Password')
                                                ->password()
                                                ->revealable()
                                                ->required($isCreate)
                                                ->confirmed()
                                                ->rule(app(PasswordRuleBuilder::class)->build())
                                                ->dehydrated(fn (?string $state): bool => filled($state))
                                                ->dehydrateStateUsing(fn (string $state): string => $state),

                                            TextInput::make('password_confirmation')
                                                ->label('Confirm Password')
                                                ->password()
                                                ->revealable()
                                                ->required($isCreate)
                                                ->dehydrated(false),
                                        ]),

                                        Grid::make(2)->schema([
                                            Select::make('status')
                                                ->label('Status')
                                                ->options([
                                                    'active' => 'Active',
                                                    'inactive' => 'Inactive',
                                                ])
                                                ->default('active')
                                                ->required()
                                                ->native(false),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Roles & Permissions')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Assign Roles')
                                    ->description('Select one or more roles to assign to this user.')
                                    ->icon('heroicon-o-key')
                                    ->schema([
                                        Select::make('roles')
                                            ->label('Roles')
                                            ->relationship('roles', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Profile')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Profile Picture')
                                    ->description('Upload an optional profile avatar.')
                                    ->icon('heroicon-o-camera')
                                    ->schema([
                                        FileUpload::make('avatar')
                                            ->label('Avatar')
                                            ->image()
                                            ->disk('public')
                                            ->directory('avatars')
                                            ->imageEditor()
                                            ->circleCropper()
                                            ->maxSize(2048)
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
