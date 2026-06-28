<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Rendering\ContentRenderer;
use App\Models\Page;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function show(string $slug, ContentRenderer $renderer): Response
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->firstOrFail();

        return response($renderer->render($page), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function home(ContentRenderer $renderer): Response
    {
        $homePage = Page::query()
            ->published()
            ->where('slug', 'home')
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->first();

        if ($homePage) {
            return response($renderer->render($homePage), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response()->view('home');
    }
}
