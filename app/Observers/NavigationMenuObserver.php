<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationCacheInterface;

class NavigationMenuObserver
{
    public function __construct(
        private readonly NavigationCacheInterface $cache,
    ) {}

    public function updated(NavigationMenu $menu): void
    {
        $this->cache->invalidateForMenu($menu->id);
    }

    public function deleted(NavigationMenu $menu): void
    {
        $this->cache->invalidateForMenu($menu->id);
    }
}
