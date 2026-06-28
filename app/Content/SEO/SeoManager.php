<?php

declare(strict_types=1);

namespace App\Content\SEO;

use App\Models\Page;
use App\Models\Post;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;

/**
 * Centralises all SEO metadata and structured-data generation.
 *
 * Extracted from PageRenderService so that the same logic can be consumed
 * by ContentRenderer, API endpoints, sitemaps, and any future content type
 * without coupling those consumers to the full renderer.
 */
class SeoManager
{
    public function __construct(
        private readonly GeneralSettings $generalSettings,
        private readonly SeoSettings $seoSettings,
    ) {}

    // ── Pages ────────────────────────────────────────────────────────────

    public function getPageMetadata(Page $page): array
    {
        $pageUrl   = $this->pageUrl($page);
        $appName   = $this->appName();
        $globalSeo = $this->seoSettings;

        $title       = $page->meta_title       ?: ($globalSeo?->meta_title       ?: ($page->title   ?: $appName));
        $description = $page->meta_description ?: ($globalSeo?->meta_description ?: ($page->excerpt ?: $this->firstCharsOfContent($page->content) ?: "Read more on {$appName}."));
        $keywords    = $page->meta_keywords    ?: ($globalSeo?->meta_keywords    ?? null);
        $robots      = $this->normaliseRobots($page->robots ?: ($globalSeo?->robots ?? 'index, follow'));
        $canonical   = $page->canonical_url    ?: ($globalSeo?->canonical_url    ?: $pageUrl);
        $ogImage     = $this->resolveOgImage((string) $page->featured_image_url);

        return [
            'title'       => $title,
            'description' => $description,
            'keywords'    => $keywords,
            'canonical'   => $canonical,
            'robots'      => $robots,
            'og_title'    => $title,
            'og_description' => $description,
            'og_image'    => $ogImage,
            'og_url'      => $pageUrl,
            'og_type'     => 'website',
            'twitter_card' => $globalSeo?->twitter_card ?? 'summary_large_image',
        ];
    }

    public function getPageStructuredData(Page $page): array
    {
        $seo = $this->getPageMetadata($page);

        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebPage',
            'name'          => $page->title,
            'description'   => $page->excerpt ?: ($seo['description'] ?? ''),
            'url'           => $this->pageUrl($page),
            'image'         => (string) $page->featured_image_url,
            'datePublished' => $page->published_at?->toIso8601String(),
            'dateModified'  => $page->updated_at->toIso8601String(),
        ];
    }

    // ── Posts ────────────────────────────────────────────────────────────

    public function getPostMetadata(Post $post): array
    {
        $postUrl   = $this->postUrl($post);
        $appName   = $this->appName();
        $globalSeo = $this->seoSettings;

        $title       = $post->meta_title       ?: ($globalSeo?->meta_title       ?: ($post->title   ?: $appName));
        $description = $post->meta_description ?: ($globalSeo?->meta_description ?: ($post->excerpt ?: $this->firstCharsOfContent($post->content) ?: "Read more on {$appName}."));
        $keywords    = $post->meta_keywords    ?: ($globalSeo?->meta_keywords    ?? null);
        $robots      = $this->normaliseRobots($post->robots ?: ($globalSeo?->robots ?? 'index, follow'));
        $canonical   = $post->canonical_url    ?: ($globalSeo?->canonical_url    ?: $postUrl);
        $ogImage     = $this->resolveOgImage((string) $post->featured_image_url);

        return [
            'title'          => $title,
            'description'    => $description,
            'keywords'       => $keywords,
            'canonical'      => $canonical,
            'robots'         => $robots,
            'og_title'       => $title,
            'og_description' => $description,
            'og_image'       => $ogImage,
            'og_url'         => $postUrl,
            'og_type'        => 'article',
            'twitter_card'   => $globalSeo?->twitter_card ?? 'summary_large_image',
        ];
    }

    public function getPostStructuredData(Post $post): array
    {
        $seo = $this->getPostMetadata($post);

        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post->title,
            'description'   => $post->excerpt ?: ($seo['description'] ?? ''),
            'url'           => $this->postUrl($post),
            'image'         => (string) $post->featured_image_url,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified'  => $post->updated_at->toIso8601String(),
            'author'        => [
                '@type' => 'Person',
                'name'  => $post->author?->name ?? config('app.name'),
            ],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function pageUrl(Page $page): string
    {
        return $page->slug === 'home' ? route('home') : route('page.show', $page->slug);
    }

    public function postUrl(Post $post): string
    {
        return route('blog.show', $post->slug);
    }

    private function appName(): string
    {
        return $this->generalSettings?->app_name ?? config('app.name');
    }

    private function normaliseRobots(string $robots): string
    {
        return str_contains($robots, ',') ? str_replace(',', ', ', $robots) : $robots;
    }

    private function firstCharsOfContent(?string $html, int $length = 160): string
    {
        if (blank($html)) {
            return '';
        }

        return str(strip_tags($html))->squish()->limit($length, '')->toString();
    }

    private function resolveOgImage(string $ogImage): string
    {
        $path = ($ogImage === '' && $this->seoSettings?->og_image)
            ? $this->seoSettings->og_image
            : $ogImage;

        return $this->toStorageUrl($path);
    }

    private function toStorageUrl(?string $path): string
    {
        if (blank($path)) {
            return '';
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '//')) {
            return $path;
        }
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
