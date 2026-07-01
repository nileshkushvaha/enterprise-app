<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Permission-aware quick action cards rendered on the Admin Dashboard.
 *
 * Each action defines a view_permission (ViewAny:*) as the gate and an
 * optional create_permission (Create:*). If the user has the create
 * permission, the card links to the create page and uses the create label.
 * If they only have ViewAny, they land on the resource index instead.
 *
 * Extensibility: append an entry to catalog(), ensure permissions exist
 * (Shield generates them automatically on shield:generate). Nothing else.
 */
class QuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<int, array{label: string, icon: string, url: string, description: string, color: string}>
     */
    public function getActions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $visible = [];

        foreach ($this->catalog() as $action) {
            $canView = $user->can($action['view_permission']);
            $canCreate = isset($action['create_permission']) && $user->can($action['create_permission']);

            if (! $canView && ! $canCreate) {
                continue;
            }

            $visible[] = [
                'label' => $canCreate ? $action['create_label'] : $action['view_label'],
                'description' => $canCreate ? $action['create_desc'] : $action['view_desc'],
                'icon' => $canCreate ? $action['create_icon'] : $action['view_icon'],
                'url' => $canCreate ? $action['create_url'] : $action['view_url'],
                'color' => $action['color'],
            ];
        }

        // System tools have no ViewAny/Create split — append separately
        foreach ($this->systemCatalog() as $action) {
            if ($user->can($action['permission'])) {
                $visible[] = $action;
            }
        }

        return $visible;
    }

    /**
     * Resource actions: each has a view (index) variant and an optional create variant.
     * Managers typically have ViewAny:* but not Create:*, so they see the index cards.
     *
     * @return array<int, array<string, string>>
     */
    private function catalog(): array
    {
        return [
            [
                'view_permission' => 'ViewAny:User',
                'view_label' => 'Users',
                'view_desc' => 'Browse user accounts',
                'view_icon' => 'heroicon-o-users',
                'view_url' => route('filament.admin.resources.users.index'),
                'create_permission' => 'Create:User',
                'create_label' => 'Create User',
                'create_desc' => 'Add a new user account',
                'create_icon' => 'heroicon-o-user-plus',
                'create_url' => route('filament.admin.resources.users.create'),
                'color' => 'blue',
            ],
            [
                'view_permission' => 'ViewAny:Page',
                'view_label' => 'Pages',
                'view_desc' => 'Browse CMS pages',
                'view_icon' => 'heroicon-o-document',
                'view_url' => route('filament.admin.resources.pages.index'),
                'create_permission' => 'Create:Page',
                'create_label' => 'New Page',
                'create_desc' => 'Publish a new CMS page',
                'create_icon' => 'heroicon-o-document-plus',
                'create_url' => route('filament.admin.resources.pages.create'),
                'color' => 'violet',
            ],
            [
                'view_permission' => 'ViewAny:Post',
                'view_label' => 'Posts',
                'view_desc' => 'Browse blog posts',
                'view_icon' => 'heroicon-o-newspaper',
                'view_url' => route('filament.admin.resources.posts.index'),
                'create_permission' => 'Create:Post',
                'create_label' => 'New Post',
                'create_desc' => 'Write a new blog post',
                'create_icon' => 'heroicon-o-pencil-square',
                'create_url' => route('filament.admin.resources.posts.create'),
                'color' => 'emerald',
            ],
            [
                'view_permission' => 'ViewAny:Role',
                'view_label' => 'Roles',
                'view_desc' => 'View roles & permissions',
                'view_icon' => 'heroicon-o-shield-check',
                'view_url' => route('filament.admin.resources.roles.index'),
                'create_permission' => 'Create:Role',
                'create_label' => 'Create Role',
                'create_desc' => 'Define a new role',
                'create_icon' => 'heroicon-o-shield-exclamation',
                'create_url' => route('filament.admin.resources.roles.create'),
                'color' => 'amber',
            ],
            [
                'view_permission' => 'ViewAny:Activity',
                'view_label' => 'Activity Log',
                'view_desc' => 'Browse audit trail',
                'view_icon' => 'heroicon-o-clipboard-document-list',
                'view_url' => route('filament.admin.resources.activity-logs.index'),
                'color' => 'indigo',
            ],
            [
                'view_permission' => 'ViewAny:LoginHistory',
                'view_label' => 'Login History',
                'view_desc' => 'Review login events',
                'view_icon' => 'heroicon-o-finger-print',
                'view_url' => route('filament.admin.resources.login-history.index'),
                'color' => 'teal',
            ],
        ];
    }

    /**
     * System / settings actions: single permission, single destination.
     *
     * @return array<int, array<string, string>>
     */
    private function systemCatalog(): array
    {
        return [
            [
                'label' => 'Settings',
                'description' => 'Application configuration',
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => route('filament.admin.pages.settings.general'),
                'permission' => 'View:GeneralSettingsPage',
                'color' => 'slate',
            ],
            [
                'label' => 'Security',
                'description' => 'Authentication & access control',
                'icon' => 'heroicon-o-lock-closed',
                'url' => route('filament.admin.pages.security.authentication'),
                'permission' => 'security.authentication.view',
                'color' => 'rose',
            ],
            [
                'label' => 'Cache Manager',
                'description' => 'Clear & optimise caches',
                'icon' => 'heroicon-o-circle-stack',
                'url' => route('filament.admin.pages.system.cache-manager'),
                'permission' => 'cache_manager.view',
                'color' => 'cyan',
            ],
            [
                'label' => 'Queue Monitor',
                'description' => 'Monitor background jobs',
                'icon' => 'heroicon-o-queue-list',
                'url' => route('filament.admin.pages.system.queue-monitor'),
                'permission' => 'queue_monitor.view',
                'color' => 'orange',
            ],
        ];
    }
}
