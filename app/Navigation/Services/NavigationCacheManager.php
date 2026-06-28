<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Enums\Navigation\NavigationLocation;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationCacheInterface;
use App\Navigation\DTOs\NavigationTree;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class NavigationCacheManager implements NavigationCacheInterface
{
    private const INDEX_KEY = 'nav:tree:index';

    private const KEY_PREFIX = 'nav:tree:';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function get(string $key): ?NavigationTree
    {
        $value = $this->cache->get($key);

        return $value instanceof NavigationTree ? $value : null;
    }

    public function put(string $key, NavigationTree $tree, int $ttl = 3600): void
    {
        $this->cache->put($key, $tree, $ttl);
        $this->trackKey($tree->id, $key);
    }

    public function invalidateForMenu(string $navigationId): void
    {
        $index = $this->loadIndex();

        foreach ($index[$navigationId] ?? [] as $key) {
            $this->cache->forget($key);
        }

        unset($index[$navigationId]);
        $this->saveIndex($index);
    }

    public function invalidateForLocation(NavigationLocation $location): void
    {
        $index = $this->loadIndex();
        $updated = $index;

        foreach ($index as $menuId => $keys) {
            foreach ($keys as $key) {
                if (str_contains($key, ':'.$location->value.':')) {
                    $this->cache->forget($key);
                    $updated[$menuId] = array_filter(
                        $updated[$menuId],
                        fn (string $k) => $k !== $key,
                    );
                }
            }

            if (empty($updated[$menuId])) {
                unset($updated[$menuId]);
            }
        }

        $this->saveIndex($updated);
    }

    public function flush(): void
    {
        $index = $this->loadIndex();

        foreach ($index as $keys) {
            foreach ($keys as $key) {
                $this->cache->forget($key);
            }
        }

        $this->cache->forget(self::INDEX_KEY);
    }

    public function cacheKey(NavigationMenu $menu, ?string $locale = null): string
    {
        return self::KEY_PREFIX.$menu->location->value.':'.$menu->slug.':'.($locale ?? 'default');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function trackKey(string $menuId, string $key): void
    {
        $index = $this->loadIndex();
        $existing = $index[$menuId] ?? [];
        $existing[] = $key;
        $index[$menuId] = array_unique($existing);

        $this->saveIndex($index);
    }

    /** @return array<string, string[]> */
    private function loadIndex(): array
    {
        return $this->cache->get(self::INDEX_KEY, []);
    }

    /** @param array<string, string[]> $index */
    private function saveIndex(array $index): void
    {
        $this->cache->forever(self::INDEX_KEY, $index);
    }
}
