<?php

declare(strict_types=1);

namespace App\Navigation\Contracts;

use App\Enums\Navigation\NavigationLocation;
use App\Models\NavigationMenu;
use App\Navigation\DTOs\NavigationTree;

interface NavigationCacheInterface
{
    public function get(string $key): ?NavigationTree;

    public function put(string $key, NavigationTree $tree, int $ttl = 3600): void;

    public function invalidateForMenu(string $navigationId): void;

    public function invalidateForLocation(NavigationLocation $location): void;

    public function flush(): void;

    public function cacheKey(NavigationMenu $menu, ?string $locale = null): string;
}
