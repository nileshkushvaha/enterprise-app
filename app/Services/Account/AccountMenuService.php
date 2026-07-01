<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;

/**
 * Builds the permission-driven Account Portal sidebar menu.
 *
 * PortalResolver::frontendMenu() is off-limits to modify and is not
 * permission-aware, so this service supersedes it for sidebar rendering.
 * PortalResolver still owns portal (WHERE) resolution; this service only
 * decides which already-portal-scoped menu items a user may see (WHAT).
 *
 * Each item: label, route (named route for the URL + active-state match),
 * icon, permission (null = always visible once on the portal), badge
 * (nullable), children (nested items, same shape — one level supported).
 */
final class AccountMenuService
{
    /**
     * @return array<int, array{label: string, url: string, route: string, icon: string, permission: ?string, badge: mixed, children: array}>
     */
    public function items(User $user): array
    {
        return array_values(array_filter(array_map(
            fn (array $item): ?array => $this->resolve($item, $user),
            $this->definitions(),
        )));
    }

    /**
     * @return array<int, array{label: string, route: string, icon: string, permission: ?string, badge?: mixed, children?: array}>
     */
    private function definitions(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'icon' => 'home',
                'permission' => null,
            ],
            [
                'label' => 'My Profile',
                'route' => 'profile.show',
                'icon' => 'user',
                'permission' => 'profile.view',
            ],
        ];
    }

    private function resolve(array $item, User $user): ?array
    {
        if (! $this->isVisible($user, $item['permission'] ?? null)) {
            return null;
        }

        $children = array_values(array_filter(array_map(
            fn (array $child): ?array => $this->resolve($child, $user),
            $item['children'] ?? [],
        )));

        return [
            'label' => $item['label'],
            'url' => route($item['route']),
            'route' => $item['route'],
            'icon' => $item['icon'] ?? 'default',
            'badge' => $item['badge'] ?? null,
            'children' => $children,
        ];
    }

    private function isVisible(User $user, ?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        try {
            return $user->can($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
