<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use App\Services\Permission\PermissionGroupingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $service = app(PermissionGroupingService::class);
        $grouped = $service->grouped();

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
                                    'active' => 'Active',
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
                Section::make('Permission Assignment')
                    ->description('Select the permissions for this role. Changes take effect immediately on save.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        View::make('filament.roles.permission-matrix')
                            ->viewData([
                                'grouped' => $grouped,
                                'allPermissions' => $service->allNames(),
                                'total' => $service->total(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
