<?php

declare(strict_types=1);

return [
    'cache' => [
        'page_render_ttl' => (int) env('CMS_PAGE_RENDER_CACHE_TTL', 3600),
        'search_ttl' => (int) env('CMS_SEARCH_CACHE_TTL', 300),
        'sitemap_ttl' => (int) env('CMS_SITEMAP_CACHE_TTL', 900),
        'robots_ttl' => (int) env('CMS_ROBOTS_CACHE_TTL', 3600),
    ],
];
