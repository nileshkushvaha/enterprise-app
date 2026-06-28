<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Rendering\ContentRenderer;
use App\Models\Page;
use App\Models\Post;
use App\Settings\GeneralSettings;
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

    public function home(ContentRenderer $renderer, GeneralSettings $settings): Response
    {
        // ── WordPress-style reading setting ──────────────────────────────
        if (($settings->homepage_display ?? 'template') === 'static_page' && filled($settings->homepage_id)) {
            $staticPage = Page::query()
                ->published()
                ->where('id', $settings->homepage_id)
                ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->first();

            if ($staticPage) {
                return response($renderer->render($staticPage), 200)
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
        }

        // ── Default: render the custom home.blade.php template ───────────
        $recentPosts = Post::query()
            ->published()
            ->with(['author', 'categories', 'media'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return response()->view('home', [
            'appName' => $settings->app_name ?? config('app.name'),
            'appShortName' => $settings->app_short_name ?? null,
            'logo' => $settings->logo ?? null,
            'supportEmail' => $settings->support_email ?? null,
            'supportPhone' => $settings->support_phone ?? null,
            'address' => $settings->address ?? null,
            'footerText' => $settings->footer_text ?? null,
            'footerCopyright' => $settings->footer_copyright ?? null,
            'recentPosts' => $recentPosts,
        ]);
    }
}
