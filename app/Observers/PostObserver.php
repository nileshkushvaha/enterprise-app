<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\CmsCacheService;
use App\Services\PageRenderService;
use App\Services\PostService;

class PostObserver
{
    public function created(Post $post): void
    {
        app(PostService::class)->refreshReadingTime($post);
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function updated(Post $post): void
    {
        app(PostService::class)->refreshReadingTime($post);
        app(PageRenderService::class)->invalidatePostCache($post);
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function deleted(Post $post): void
    {
        app(PageRenderService::class)->invalidatePostCache($post);
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function restored(Post $post): void
    {
        app(PostService::class)->refreshReadingTime($post);
        app(PageRenderService::class)->invalidatePostCache($post);
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function forceDeleted(Post $post): void
    {
        app(PageRenderService::class)->invalidatePostCache($post);
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }
}

