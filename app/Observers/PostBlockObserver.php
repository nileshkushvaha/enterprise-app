<?php

namespace App\Observers;

use App\Models\PostBlock;
use App\Services\PageRenderService;
use App\Services\PostService;

class PostBlockObserver
{
    public function created(PostBlock $block): void
    {
        app(PostService::class)->refreshReadingTime($block->post);
        app(PageRenderService::class)->invalidatePostCache($block->post);
    }

    public function updated(PostBlock $block): void
    {
        app(PostService::class)->refreshReadingTime($block->post);
        app(PageRenderService::class)->invalidatePostCache($block->post);
    }

    public function deleted(PostBlock $block): void
    {
        app(PostService::class)->refreshReadingTime($block->post);
        app(PageRenderService::class)->invalidatePostCache($block->post);
    }

    public function restored(PostBlock $block): void
    {
        app(PostService::class)->refreshReadingTime($block->post);
        app(PageRenderService::class)->invalidatePostCache($block->post);
    }
}

