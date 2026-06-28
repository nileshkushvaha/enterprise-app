<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Support\PermissionLabelFormatter;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Loads, groups, and enriches permissions for the UI permission matrix.
 *
 * Fully read-only — never modifies Spatie's tables.
 */
final class PermissionGroupingService
{
    /**
     * Return all permissions grouped by module, sorted by config order.
     *
     * @return Collection<string, array{
     *     module: string,
     *     title: string,
     *     icon: string,
     *     order: int,
     *     permissions: Collection<int, array{name: string, label: string}>
     * }>
     */
    public function grouped(): Collection
    {
        $permissions = $this->loadPermissions();

        return $permissions
            ->groupBy(fn (string $name) => PermissionLabelFormatter::extractModule($name))
            ->map(fn (Collection $perms, string $module) => [
                'module' => $module,
                'title' => PermissionLabelFormatter::moduleLabel($perms->first()),
                'icon' => PermissionLabelFormatter::moduleIcon($module),
                'order' => PermissionLabelFormatter::moduleOrder($module),
                'permissions' => $perms->map(fn (string $name) => [
                    'name' => $name,
                    'label' => PermissionLabelFormatter::actionLabel($name),
                ])->sortBy('label')->values(),
            ])
            ->sortBy('order')
            ->values()
            ->keyBy('module');
    }

    /**
     * Return flat array of all permission names (for syncPermissions).
     *
     * @return array<string>
     */
    public function allNames(): array
    {
        return $this->loadPermissions()->toArray();
    }

    /**
     * Total count of permissions.
     */
    public function total(): int
    {
        return Permission::count();
    }

    /**
     * Load permission names once — no N+1 queries.
     *
     * @return Collection<int, string>
     */
    private function loadPermissions(): Collection
    {
        return Permission::orderBy('name')->pluck('name');
    }
}
