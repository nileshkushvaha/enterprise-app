<?php

namespace App\Http\Controllers;

use App\Services\CmsCacheService;
use App\Services\PageService;
use App\Services\PostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(
        Request $request,
        CmsCacheService $cacheService,
        PageService $pageService,
        PostService $postService
    ): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        $results = Cache::remember(
            $cacheService->searchKey($query, 1),
            now()->addSeconds(max(1, (int) config('cms.cache.search_ttl', 300))),
            function () use ($query, $pageService, $postService): array {
                return [
                    'pages' => $pageService->searchPublishedPages($query),
                    'posts' => $postService->searchPublishedPosts($query),
                ];
            }
        );

        $pageCount = $results['pages']->count();
        $postCount = $results['posts']->count();

        $seo = [
            'title' => $query === '' ? 'Search' : "Search results for \"{$query}\"",
            'description' => 'Search published pages and posts.',
            'keywords' => 'search,pages,posts',
            'robots' => 'noindex, follow',
            'canonical' => route('search.index', array_filter(['q' => $query])),
            'og_title' => $query === '' ? 'Search' : "Search results for \"{$query}\"",
            'og_description' => 'Search published pages and posts.',
            'og_url' => route('search.index', array_filter(['q' => $query])),
            'og_image' => null,
            'og_type' => 'website',
        ];

        return view('pages.search', [
            'query' => $query,
            'results' => $results,
            'totalResults' => $pageCount + $postCount,
            'seo' => $seo,
        ]);
    }
}
