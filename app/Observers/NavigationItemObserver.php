<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\NavigationItem;
use App\Navigation\Contracts\NavigationCacheInterface;
use LogicException;

class NavigationItemObserver
{
    public function __construct(
        private readonly NavigationCacheInterface $cache,
    ) {}

    public function saving(NavigationItem $item): void
    {
        $this->guardCircularParent($item);
    }

    public function created(NavigationItem $item): void
    {
        $this->cache->invalidateForMenu($item->navigation_id);
    }

    public function updated(NavigationItem $item): void
    {
        $this->cache->invalidateForMenu($item->navigation_id);
    }

    public function deleted(NavigationItem $item): void
    {
        $this->cache->invalidateForMenu($item->navigation_id);
    }

    public function restored(NavigationItem $item): void
    {
        $this->cache->invalidateForMenu($item->navigation_id);
    }

    private function guardCircularParent(NavigationItem $item): void
    {
        if ($item->parent_id === null) {
            return;
        }

        // Self-reference
        if ($item->exists && $item->id === $item->parent_id) {
            throw new LogicException('A navigation item cannot be its own parent.');
        }

        // Descendant-as-parent: the proposed parent must not sit inside this item's subtree.
        if ($item->exists && $item->_lft > 0) {
            $parent = NavigationItem::withTrashed()->find($item->parent_id);

            if ($parent !== null
                && $parent->_lft >= $item->_lft
                && $parent->_rgt <= $item->_rgt
            ) {
                throw new LogicException('Cannot set a descendant as the parent — circular reference detected.');
            }
        }
    }
}
