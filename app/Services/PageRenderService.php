<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Post;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Collection;

class PageRenderService
{
    public function __construct(
        private readonly BlockRenderer $blockRenderer,
        private readonly GeneralSettings $generalSettings,
        private readonly SeoSettings $seoSettings,
    ) {}

    /**
     * Render a complete page with layout
     */
    public function render(Page $page): string
    {
        return cache()->remember(
            $this->pageRenderCacheKey($page),
            now()->addSeconds($this->pageRenderTtl()),
            fn () => $this->renderPageContent($page)
        );
    }

    /**
     * Render page content without caching (for preview)
     */
    public function renderPreview(Page $page): string
    {
        return $this->renderPageContent($page);
    }

    public function renderPost(Post $post): string
    {
        return cache()->remember(
            $this->postRenderCacheKey($post),
            now()->addSeconds($this->pageRenderTtl()),
            fn () => $this->renderPostContent($post)
        );
    }

    public function renderPostPreview(Post $post): string
    {
        return $this->renderPostContent($post);
    }

    /**
     * Invalidate page cache
     */
    public function invalidateCache(?Page $page): void
    {
        if (! $page) {
            return;
        }

        cache()->forget($this->pageRenderCacheKey($page));
    }

    public function invalidatePostCache(?Post $post): void
    {
        if (! $post) {
            return;
        }

        cache()->forget($this->postRenderCacheKey($post));
    }

    /**
     * Get SEO metadata for a page
     */
    public function getSeoMetadata(Page $page): array
    {
        $pageUrl = $this->getPageUrl($page);
        $general = $this->generalSettings;
        $globalSeo = $this->seoSettings;

        $appName = $general?->app_name ?? config('app.name');
        $title = $page->meta_title ?: ($globalSeo?->meta_title ?: ($page->title ?: $appName));
        $description = $page->meta_description ?: ($globalSeo?->meta_description ?: ($page->excerpt ?: "Read more on {$appName}."));
        $keywords = $page->meta_keywords ?: ($globalSeo?->meta_keywords ?? null);
        $robots = $page->robots ?: ($globalSeo?->robots ?? 'index, follow');
        $canonical = $page->canonical_url ?: ($globalSeo?->canonical_url ?: $pageUrl);
        $ogImage = (string) $page->featured_image_url;
        if ($ogImage === '' && $globalSeo?->og_image) {
            $ogImage = $globalSeo->og_image;
        }

        $robots = str_contains($robots, ',') ? str_replace(',', ', ', $robots) : $robots;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_url' => $pageUrl,
            'og_type' => 'website',
            'twitter_card' => $globalSeo?->twitter_card ?? 'summary_large_image',
        ];
    }

    /**
     * Get JSON-LD structured data
     */
    public function getStructuredData(Page $page): array
    {
        $pageUrl = $this->getPageUrl($page);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'description' => $page->excerpt ?: ($this->getSeoMetadata($page)['description'] ?? ''),
            'url' => $pageUrl,
            'image' => (string) $page->featured_image_url,
            'datePublished' => $page->published_at?->toIso8601String(),
            'dateModified' => $page->updated_at->toIso8601String(),
        ];
    }

    public function getPostSeoMetadata(Post $post): array
    {
        $postUrl = $this->getPostUrl($post);
        $general = $this->generalSettings;
        $globalSeo = $this->seoSettings;

        $appName = $general?->app_name ?? config('app.name');
        $title = $post->meta_title ?: ($globalSeo?->meta_title ?: ($post->title ?: $appName));
        $description = $post->meta_description ?: ($globalSeo?->meta_description ?: ($post->excerpt ?: "Read more on {$appName}."));
        $keywords = $post->meta_keywords ?: ($globalSeo?->meta_keywords ?? null);
        $robots = $post->robots ?: ($globalSeo?->robots ?? 'index, follow');
        $canonical = $post->canonical_url ?: ($globalSeo?->canonical_url ?: $postUrl);
        $ogImage = (string) $post->featured_image_url;
        if ($ogImage === '' && $globalSeo?->og_image) {
            $ogImage = $globalSeo->og_image;
        }

        $robots = str_contains($robots, ',') ? str_replace(',', ', ', $robots) : $robots;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_url' => $postUrl,
            'og_type' => 'article',
            'twitter_card' => $globalSeo?->twitter_card ?? 'summary_large_image',
        ];
    }

    public function getPostStructuredData(Post $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt ?: ($this->getPostSeoMetadata($post)['description'] ?? ''),
            'url' => $this->getPostUrl($post),
            'image' => (string) $post->featured_image_url,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? config('app.name'),
            ],
        ];
    }

    private function getPageUrl(Page $page): string
    {
        return $page->slug === 'home' ? route('home') : route('page.show', $page->slug);
    }

    /**
     * Internal: Render page with layout
     */
    private function renderPageContent(Page $page): string
    {
        $blocksHtml = $this->renderBlocks($page->blocks);
        return $this->applyLayout($blocksHtml, $page, $this->getSeoMetadata($page), $this->getStructuredData($page));
    }

    private function renderPostContent(Post $post): string
    {
        $blocksHtml = $this->renderBlocks($post->blocks);

        return $this->applyLayout($blocksHtml, $post, $this->getPostSeoMetadata($post), $this->getPostStructuredData($post));
    }

    /**
     * Render all blocks for a page
     */
    private function renderBlocks(Collection $blocks): string
    {
        if ($blocks->isEmpty()) {
            return '';
        }

        return $blocks
            ->sortBy('sort_order')
            ->where('is_active', true)
            ->map(fn ($block) => $this->renderBlock($block))
            ->join("\n");
    }

    /**
     * Render a single block using BlockRenderer service
     */
    private function renderBlock(object $block): string
    {
        try {
            return $this->blockRenderer->render($block);
        } catch (\Exception $e) {
            $blockType = $block->block_type->value ?? (string) ($block->block_type ?? 'unknown');

            return $this->renderBlockError($blockType, $e->getMessage());
        }
    }

    /**
     * Apply page layout template
     */
    private function applyLayout(string $content, object $contentModel, array $seo, array $structuredData): string
    {
        $layoutName = $contentModel->layout ?? 'default';
        $layout = match ($layoutName) {
            'landing' => 'layouts.landing',
            'blank' => 'layouts.blank',
            default => 'layouts.page',
        };

        return view($layout, [
            'content' => $content,
            'page' => $contentModel,
            'post' => $contentModel instanceof Post ? $contentModel : null,
            'seo' => $seo,
            'structured_data' => $structuredData,
            'site' => $this->getSiteMetadata(),
        ])->render();
    }

    /**
     * Render block not found error (development only)
     */
    private function renderBlockNotFound(string $blockType): string
    {
        if (app()->isProduction()) {
            return '';
        }

        return sprintf(
            '<div class="alert alert-warning p-4 m-4">Block view not found: components.blocks.%s</div>',
            str($blockType)->kebab()
        );
    }

    /**
     * Render block error (development only)
     */
    private function renderBlockError(string $blockType, string $error): string
    {
        if (app()->isProduction()) {
            return '';
        }

        return sprintf(
            '<div class="alert alert-danger p-4 m-4"><strong>Block Error (%s):</strong> %s</div>',
            str($blockType)->kebab(),
            htmlspecialchars($error)
        );
    }

    private function pageRenderCacheKey(Page $page): string
    {
        return "page-render:{$page->id}";
    }

    private function pageRenderTtl(): int
    {
        return max(1, (int) config('cms.cache.page_render_ttl', 3600));
    }

    private function getSiteMetadata(): array
    {
        $general = $this->getGeneralSettings();

        if (! $general) {
            return [
                'app_name' => config('app.name'),
                'logo' => null,
                'favicon' => null,
                'footer_text' => null,
                'footer_copyright' => null,
            ];
        }

        return [
            'app_name' => $general->app_name,
            'logo' => $general->logo,
            'favicon' => $general->favicon,
            'footer_text' => $general->footer_text,
            'footer_copyright' => $general->footer_copyright,
        ];
    }

    private function getGeneralSettings(): GeneralSettings
    {
        return $this->generalSettings;
    }

    private function getSeoSettings(): SeoSettings
    {
        return $this->seoSettings;
    }

    private function postRenderCacheKey(Post $post): string
    {
        return "post-render:{$post->id}";
    }

    private function getPostUrl(Post $post): string
    {
        return route('blog.show', $post->slug);
    }
}
