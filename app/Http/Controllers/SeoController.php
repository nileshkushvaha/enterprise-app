<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Services\CmsCacheService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap(CmsCacheService $cacheService): Response
    {
        $xml = Cache::remember(
            $cacheService->sitemapKey(),
            now()->addSeconds(max(1, (int) config('cms.cache.sitemap_ttl', 900))),
            function (): string {
                $pages = Page::query()
                    ->published()
                    ->select(['slug', 'updated_at'])
                    ->latest('updated_at')
                    ->get();

                $posts = Post::query()
                    ->published()
                    ->select(['slug', 'updated_at'])
                    ->latest('updated_at')
                    ->get();

                return view('seo.sitemap', ['pages' => $pages, 'posts' => $posts])->render();
            }
        );

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(CmsCacheService $cacheService): Response
    {
        $content = Cache::remember(
            $cacheService->robotsKey(),
            now()->addSeconds(max(1, (int) config('cms.cache.robots_ttl', 3600))),
            static fn (): string => implode("\n", [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /login',
                'Sitemap: '.route('seo.sitemap'),
                '',
            ])
        );

        return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
