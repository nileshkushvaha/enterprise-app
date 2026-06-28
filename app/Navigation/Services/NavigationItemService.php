<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Enums\Navigation\NavigationLinkType;
use App\Enums\Navigation\NavigationVisibility;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationCacheInterface;
use App\Navigation\DTOs\NavigationItemData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NavigationItemService
{
    public function __construct(
        private readonly NavigationCacheInterface $cache,
    ) {}

    // ── Queries ────────────────────────────────────────────────────────────

    public function findItem(string $id): ?NavigationItem
    {
        return NavigationItem::with(['roles', 'permissions'])->find($id);
    }

    /**
     * Returns the menu's items as a nested PHP array for the builder UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTreeArray(NavigationMenu $menu): array
    {
        $items = NavigationItem::where('navigation_id', $menu->id)
            ->with(['roles', 'permissions'])
            ->orderBy('_lft')
            ->get();

        return $this->buildTreeArray($items, null);
    }

    // ── Mutations ──────────────────────────────────────────────────────────

    public function createForLinkable(
        NavigationMenu $menu,
        string $morphType,
        string $morphId,
        string $label,
        NavigationLinkType $linkType,
        ?string $parentId = null,
    ): NavigationItem {
        $data = new NavigationItemData(
            label: $label,
            linkType: $linkType,
            linkableType: $morphType,
            linkableId: $morphId,
            parentId: $parentId,
        );

        return $this->create($menu, $data);
    }

    public function createForUrl(
        NavigationMenu $menu,
        NavigationLinkType $linkType,
        string $url,
        string $label,
        ?string $parentId = null,
    ): NavigationItem {
        $data = new NavigationItemData(
            label: $label,
            linkType: $linkType,
            url: $url,
            parentId: $parentId,
        );

        return $this->create($menu, $data);
    }

    public function create(NavigationMenu $menu, NavigationItemData $data): NavigationItem
    {
        $this->validateScheduling($data);

        $item = new NavigationItem;
        $item->navigation_id = $menu->id;
        $this->fillItem($item, $data);

        $sortOrder = NavigationItem::where('navigation_id', $menu->id)
            ->whereNull('parent_id')
            ->max('sort_order') ?? -1;
        $item->sort_order = $sortOrder + 1;

        if ($data->parentId !== null) {
            $parent = NavigationItem::where('id', $data->parentId)
                ->where('navigation_id', $menu->id)
                ->first();

            if ($parent !== null) {
                $item->appendToNode($parent)->save();
                $this->syncPivots($item, $data);
                $this->cache->invalidateForMenu($menu->id);

                return $item;
            }
        }

        $item->saveAsRoot();
        $this->syncPivots($item, $data);
        $this->cache->invalidateForMenu($menu->id);

        return $item;
    }

    public function update(NavigationItem $item, NavigationItemData $data): NavigationItem
    {
        $this->validateScheduling($data);
        $this->fillItem($item, $data);
        $item->save();
        $this->syncPivots($item, $data);
        $this->cache->invalidateForMenu($item->navigation_id);

        return $item;
    }

    public function delete(NavigationItem $item): void
    {
        $navigationId = $item->navigation_id;
        $item->delete();
        $this->cache->invalidateForMenu($navigationId);
    }

    public function duplicate(NavigationItem $item): NavigationItem
    {
        $new = $item->replicate();
        $new->label = $item->label.' (Copy)';
        $new->sort_order = $item->sort_order + 1;

        if ($item->parent_id !== null) {
            $parent = NavigationItem::find($item->parent_id);
            if ($parent !== null) {
                $new->appendToNode($parent)->save();
            } else {
                $new->saveAsRoot();
            }
        } else {
            $new->saveAsRoot();
        }

        $new->roles()->sync($item->roles->pluck('id'));
        $new->permissions()->sync($item->permissions->pluck('id'));

        $this->cache->invalidateForMenu($item->navigation_id);

        return $new;
    }

    /**
     * Reorder items from a drag-and-drop serialization.
     *
     * @param  array<int, array{id: string, parentId: string|null, sortOrder: int}>  $items
     */
    public function reorder(NavigationMenu $menu, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $menuId = $menu->id;
        $allowedIds = NavigationItem::where('navigation_id', $menuId)
            ->pluck('id')
            ->flip()
            ->toArray();

        DB::transaction(function () use ($items, $menuId, $allowedIds): void {
            foreach ($items as $item) {
                $id = $item['id'] ?? '';
                if (! isset($allowedIds[$id])) {
                    continue;
                }

                $parentId = $item['parentId'] ?? null;
                if ($parentId !== null && ! isset($allowedIds[$parentId])) {
                    $parentId = null;
                }

                NavigationItem::where('id', $id)
                    ->where('navigation_id', $menuId)
                    ->update([
                        'parent_id' => $parentId,
                        'sort_order' => (int) ($item['sortOrder'] ?? 0),
                    ]);
            }

            // withoutEvents: fixTree() only updates _lft/_rgt/depth — cache is
            // invalidated explicitly below, so observer calls are redundant here.
            NavigationItem::withoutEvents(static fn () => NavigationItem::fixTree());
        });

        $this->cache->invalidateForMenu($menu->id);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function fillItem(NavigationItem $item, NavigationItemData $data): void
    {
        $item->label = $data->label;
        $item->link_type = $data->linkType;
        $item->url = $data->url;
        $item->route_name = $data->routeName;
        $item->route_params = $data->routeParams ?: null;
        $item->linkable_type = $data->linkableType;
        $item->linkable_id = $data->linkableId;
        $item->target = $data->target;
        $item->rel = $data->rel;
        $item->icon = $data->icon;
        $item->css_class = $data->cssClass;
        $item->css_id = $data->cssId;
        $item->badge_text = $data->badgeText;
        $item->badge_color = $data->badgeColor;
        $item->visibility = $data->visibility;
        $item->is_active = $data->isActive;
        $item->open_in_modal = $data->openInModal;
        $item->extra_attributes = $data->extraAttributes ?: null;
        $item->locale = $data->locale;
        $item->publish_from = $data->publishFrom;
        $item->publish_until = $data->publishUntil;
    }

    private function syncPivots(NavigationItem $item, NavigationItemData $data): void
    {
        if ($data->visibility === NavigationVisibility::Roles) {
            $item->roles()->sync($data->requiredRoleIds);
        } else {
            $item->roles()->detach();
        }

        if ($data->visibility === NavigationVisibility::Permissions) {
            $item->permissions()->sync($data->requiredPermissionIds);
        } else {
            $item->permissions()->detach();
        }
    }

    private function validateScheduling(NavigationItemData $data): void
    {
        if ($data->locale !== null && ! preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $data->locale)) {
            throw new InvalidArgumentException(
                'locale must be a valid language tag (e.g. en, fr, en-US).'
            );
        }

        if ($data->publishFrom !== null
            && $data->publishUntil !== null
            && $data->publishUntil->lessThanOrEqualTo($data->publishFrom)
        ) {
            throw new InvalidArgumentException('publish_until must be after publish_from.');
        }
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTreeArray($items, ?string $parentId): array
    {
        return $items
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (NavigationItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'link_type' => $item->link_type->value,
                'visibility' => $item->visibility->value,
                'is_active' => $item->is_active,
                'icon' => $item->icon,
                'badge_text' => $item->badge_text,
                'badge_color' => $item->badge_color,
                'target' => $item->target,
                'locale' => $item->locale,
                'publish_from' => $item->publish_from?->toIso8601String(),
                'publish_until' => $item->publish_until?->toIso8601String(),
                'children' => $this->buildTreeArray($items, $item->id),
            ])
            ->all();
    }
}
