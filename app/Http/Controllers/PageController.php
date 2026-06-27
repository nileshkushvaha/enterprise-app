<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\PageRenderService;
use Illuminate\Http\Response;

class PageController extends Controller
{
    /**
     * Display a published page
     */
    public function show(string $slug, PageRenderService $renderService): Response
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->with(['blocks' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')])
            ->firstOrFail();

        // Check if published and has passed publication date
        if ($page->published_at && $page->published_at->isFuture()) {
            abort(404);
        }

        return response($renderService->render($page), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Homepage - special case that can show a specific page or custom home
     */
    public function home(PageRenderService $renderService): Response
    {
        // Try to load a page with slug 'home'
        $homePage = Page::query()
            ->published()
            ->where('slug', 'home')
            ->with(['blocks' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')])
            ->first();

        if ($homePage) {
            return response($renderService->render($homePage), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // Fallback to default homepage
        return response()->view('home');
    }
}
