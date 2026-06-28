<?php

namespace App\Observers;

use App\Models\Tag;
use App\Services\CmsCacheService;

class TagObserver
{
    public function created(Tag $tag): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function updated(Tag $tag): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function deleted(Tag $tag): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }

    public function restored(Tag $tag): void
    {
        app(CmsCacheService::class)->bumpDiscoveryVersion();
    }
}
