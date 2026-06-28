<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationRendererInterface;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\DTOs\PublishWindow;
use Illuminate\Database\Eloquent\Collection;

final class NavigationRenderer implements NavigationRendererInterface
{
    public function __construct(
        private readonly UrlResolver $urlResolver,
    ) {}

    public function render(NavigationMenu $menu): NavigationTree
    {
        $rootItems = $menu->items->whereNull('parent_id')->sortBy('_lft')->values();
        $nodes = $this->buildNodes($menu->items, $rootItems, 0);

        return new NavigationTree(
            id: $menu->id,
            name: $menu->name,
            slug: $menu->slug,
            location: $menu->location,
            layoutType: $menu->layout_type,
            totalNodes: $menu->items->count(),
            nodes: $nodes,
        );
    }

    /** @return list<NavigationNode> */
    private function buildNodes(Collection $all, Collection $items, int $depth): array
    {
        $nodes = [];

        foreach ($items as $item) {
            $children = $all->where('parent_id', $item->id)->sortBy('_lft')->values();
            $childNodes = $children->isEmpty() ? [] : $this->buildNodes($all, $children, $depth + 1);
            $nodes[] = $this->buildNode($item, $depth, $childNodes);
        }

        return $nodes;
    }

    /** @param list<NavigationNode> $children */
    private function buildNode(NavigationItem $item, int $depth, array $children): NavigationNode
    {
        return new NavigationNode(
            id: $item->id,
            navigationId: $item->navigation_id,
            label: $item->label,
            link: $this->urlResolver->resolve($item),
            visibility: $item->visibility,
            publishWindow: PublishWindow::from($item->publish_from, $item->publish_until),
            requiredRoleIds: $item->roles->pluck('id')->all(),
            requiredPermissionIds: $item->permissions->pluck('id')->all(),
            icon: $item->icon,
            cssClass: $item->css_class,
            cssId: $item->css_id,
            badgeText: $item->badge_text,
            badgeColor: $item->badge_color,
            isActive: false,
            isAncestorActive: false,
            depth: $depth,
            sortOrder: $item->sort_order,
            children: $children,
            locale: $item->locale,
            openInModal: $item->open_in_modal,
        );
    }
}
