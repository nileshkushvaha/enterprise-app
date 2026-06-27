<?php

namespace App\Observers;

use App\Models\PageBlock;
use App\Services\PageRenderService;

class PageBlockObserver
{
    /**
     * Handle the PageBlock "created" event.
     */
    public function created(PageBlock $block): void
    {
        app(PageRenderService::class)->invalidateCache($block->page);
    }

    /**
     * Handle the PageBlock "updated" event.
     */
    public function updated(PageBlock $block): void
    {
        app(PageRenderService::class)->invalidateCache($block->page);
    }

    /**
     * Handle the PageBlock "deleted" event.
     */
    public function deleted(PageBlock $block): void
    {
        app(PageRenderService::class)->invalidateCache($block->page);
    }

    /**
     * Handle the PageBlock "restored" event.
     */
    public function restored(PageBlock $block): void
    {
        app(PageRenderService::class)->invalidateCache($block->page);
    }
}
