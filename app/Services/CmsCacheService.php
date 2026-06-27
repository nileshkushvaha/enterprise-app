<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CmsCacheService
{
    private const DISCOVERY_VERSION_KEY = 'cms:discovery:version';

    public function getDiscoveryVersion(): int
    {
        Cache::add(self::DISCOVERY_VERSION_KEY, 1);

        return (int) Cache::get(self::DISCOVERY_VERSION_KEY, 1);
    }

    public function bumpDiscoveryVersion(): void
    {
        Cache::add(self::DISCOVERY_VERSION_KEY, 1);
        Cache::increment(self::DISCOVERY_VERSION_KEY);
    }

    public function searchKey(string $query, int $page): string
    {
        return sprintf(
            'cms:search:v%d:q:%s:p:%d',
            $this->getDiscoveryVersion(),
            sha1($query),
            $page
        );
    }

    public function sitemapKey(): string
    {
        return sprintf('cms:seo:sitemap:v%d', $this->getDiscoveryVersion());
    }

    public function robotsKey(): string
    {
        return sprintf('cms:seo:robots:v%d', $this->getDiscoveryVersion());
    }
}

