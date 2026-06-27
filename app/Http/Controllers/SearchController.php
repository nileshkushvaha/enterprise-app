<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\CmsCacheService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, CmsCacheService $cacheService): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $pageNumber = max(1, (int) $request->integer('page', 1));

        /** @var LengthAwarePaginator $results */
        $results = Cache::remember(
            $cacheService->searchKey($query, $pageNumber),
            now()->addSeconds(max(1, (int) config('cms.cache.search_ttl', 300))),
            function () use ($query): LengthAwarePaginator {
                return Page::query()
                    ->published()
                    ->when($query !== '', fn ($builder) => $builder->search($query))
                    ->latest('updated_at')
                    ->paginate(10)
                    ->withQueryString();
            }
        );

        $seo = [
            'title' => $query === '' ? 'Search' : "Search results for \"{$query}\"",
            'description' => 'Search published pages.',
            'keywords' => 'search,pages',
            'robots' => 'noindex, follow',
            'canonical' => route('search.index', array_filter(['q' => $query])),
            'og_title' => $query === '' ? 'Search' : "Search results for \"{$query}\"",
            'og_description' => 'Search published pages.',
            'og_url' => route('search.index', array_filter(['q' => $query])),
            'og_image' => null,
            'og_type' => 'website',
        ];

        return view('pages.search', [
            'query' => $query,
            'results' => $results,
            'seo' => $seo,
        ]);
    }
}
