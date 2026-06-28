<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Enums\Navigation\NavigationLocation;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationCacheInterface;
use App\Navigation\Contracts\NavigationPermissionInterface;
use App\Navigation\Contracts\NavigationRendererInterface;
use App\Navigation\Contracts\NavigationRepositoryInterface;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\NavigationTree;
use Illuminate\Contracts\Auth\Guard;

final class NavigationManager
{
    public function __construct(
        private readonly NavigationRepositoryInterface $repository,
        private readonly NavigationRendererInterface $renderer,
        private readonly NavigationCacheInterface $cache,
        private readonly ActiveDetector $activeDetector,
        private readonly NavigationPermissionInterface $permissions,
        private readonly Guard $auth,
    ) {}

    public function forLocation(NavigationLocation $location, ?string $locale = null): ?NavigationTree
    {
        $menu = $this->repository->findByLocation($location, $locale);

        if ($menu === null) {
            return null;
        }

        $cacheKey = $this->cache->cacheKey($menu, $locale);
        $tree = $this->cache->get($cacheKey);

        if ($tree === null) {
            $tree = $this->renderer->render($menu);
            $this->cache->put($cacheKey, $tree);
        }

        $tree = $this->applyPermissions($tree);
        $tree = $this->filterByLocale($tree, $locale);
        $tree = $this->activeDetector->markActive($tree);

        return $tree;
    }

    public function forSlug(string $slug, ?string $locale = null): ?NavigationTree
    {
        $menu = $this->repository->findBySlug($slug);

        if ($menu === null) {
            return null;
        }

        $cacheKey = $this->cache->cacheKey($menu);
        $tree = $this->cache->get($cacheKey);

        if ($tree === null) {
            $tree = $this->renderer->render($menu);
            $this->cache->put($cacheKey, $tree);
        }

        $tree = $this->applyPermissions($tree);
        $tree = $this->filterByLocale($tree, $locale);
        $tree = $this->activeDetector->markActive($tree);

        return $tree;
    }

    public function invalidate(string $slug): void
    {
        $menu = NavigationMenu::where('slug', $slug)->first();

        if ($menu !== null) {
            $this->cache->invalidateForMenu($menu->id);
        }
    }

    private function applyPermissions(NavigationTree $tree): NavigationTree
    {
        $user = $this->auth->user();
        $filtered = $this->filterNodes($tree->nodes, $user);

        return $tree->withNodes($filtered);
    }

    private function filterByLocale(NavigationTree $tree, ?string $locale): NavigationTree
    {
        if ($locale === null) {
            return $tree;
        }

        return $tree->withNodes($this->filterNodesByLocale($tree->nodes, $locale));
    }

    /** @return list<NavigationNode> */
    private function filterNodes(array $nodes, mixed $user): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (! $this->permissions->isVisible($node, $user)) {
                continue;
            }

            $children = $node->hasChildren()
                ? $this->filterNodes($node->children, $user)
                : [];

            $result[] = $node->withChildren($children);
        }

        return $result;
    }

    /** @return list<NavigationNode> */
    private function filterNodesByLocale(array $nodes, string $locale): array
    {
        $result = [];

        foreach ($nodes as $node) {
            // Items without a locale restriction are shown in all locales.
            if ($node->locale !== null && $node->locale !== $locale) {
                continue;
            }

            $result[] = $node->withChildren(
                $node->hasChildren() ? $this->filterNodesByLocale($node->children, $locale) : []
            );
        }

        return $result;
    }
}
