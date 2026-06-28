<?php

namespace App\Observers;

use App\Models\PostCategory;
use App\Services\CmsCacheService;

class PostCategoryObserver
{
    public function created(PostCategory $category): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function updated(PostCategory $category): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function deleted(PostCategory $category): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function restored(PostCategory $category): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }
}
