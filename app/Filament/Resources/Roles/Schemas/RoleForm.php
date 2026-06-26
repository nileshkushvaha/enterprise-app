<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Section 1: Role Information ───────────────────────────
                Section::make('Role Information')
                    ->description('Define the role name, scope, and visibility settings.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Role Name')
                                ->required()
                                ->maxLength(100)
                                ->unique(
                                    table: 'roles',
                                    column: 'name',
                                    ignoreRecord: true,
                                )
                                ->placeholder('e.g. content-manager')
                                ->helperText('Use lowercase with hyphens. Must be unique.')
                                ->columnSpan(2),

                            TextInput::make('guard_name')
                                ->label('Guard')
                                ->default('web')
                                ->required()
                                ->maxLength(50)
                                ->helperText('Auth guard this role applies to (usually "web").'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active'   => 'Active',
                                    'inactive' => 'Inactive',
                                ])
                                ->default('active')
                                ->required()
                                ->native(false)
                                ->helperText('Inactive roles are hidden in assignment dropdowns.'),

                            Textarea::make('description')
                                ->label('Description')
                                ->maxLength(500)
                                ->nullable()
                                ->rows(2)
                                ->placeholder('Briefly describe what this role can do.')
                                ->helperText('Optional. Max 500 characters.'),
                        ]),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->maxLength(500)
                            ->nullable()
                            ->rows(2)
                            ->placeholder('Internal notes (not shown to users).')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Section 2: Permission Matrix ──────────────────────────
                Section::make('Permissions')
                    ->description('Select the permissions for this role. Changes take effect immediately on save.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        View::make('filament.roles.permission-matrix')
                            ->viewData([
                                'allPermissions' => static::getAllPermissions(),
                                'modules'        => static::getModules(),
                                'actions'        => static::getActions(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string> */
    private static function getAllPermissions(): array
    {
        return Permission::orderBy('name')->pluck('name')->toArray();
    }

    /** @return array<string> Distinct modules (part after ':') */
    private static function getModules(): array
    {
        return Permission::orderBy('name')
            ->pluck('name')
            ->map(fn (string $p): string => explode(':', $p)[1] ?? $p)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /** @return array<string> Distinct actions (part before ':') */
    private static function getActions(): array
    {
        return Permission::orderBy('name')
            ->pluck('name')
            ->map(fn (string $p): string => explode(':', $p)[0] ?? $p)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
