<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Support\Collection;

class PageRenderService
{
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

    /**
     * Get SEO metadata for a page
     */
    public function getSeoMetadata(Page $page): array
    {
        $pageUrl = $this->getPageUrl($page);

        return [
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description ?: $page->excerpt,
            'keywords' => $page->meta_keywords,
            'canonical' => $page->canonical_url ?: $pageUrl,
            'robots' => $page->robots ?: 'index, follow',
            'og_title' => $page->meta_title ?: $page->title,
            'og_description' => $page->meta_description ?: $page->excerpt,
            'og_image' => (string) $page->featured_image_url,
            'og_url' => $pageUrl,
            'og_type' => 'website',
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
            'description' => $page->excerpt,
            'url' => $pageUrl,
            'image' => (string) $page->featured_image_url,
            'datePublished' => $page->published_at?->toIso8601String(),
            'dateModified' => $page->updated_at->toIso8601String(),
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
        return $this->applyLayout($blocksHtml, $page);
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
            ->map(fn (PageBlock $block) => $this->renderBlock($block))
            ->join("\n");
    }

    /**
     * Render a single block using BlockRenderer service
     */
    private function renderBlock(PageBlock $block): string
    {
        try {
            $renderer = new BlockRenderer();
            return $renderer->render($block);
        } catch (\Exception $e) {
            return $this->renderBlockError($block->block_type, $e->getMessage());
        }
    }

    /**
     * Apply page layout template
     */
    private function applyLayout(string $content, Page $page): string
    {
        $layout = match ($page->layout) {
            'landing' => 'layouts.landing',
            'blank' => 'layouts.blank',
            default => 'layouts.page',
        };

        return view($layout, [
            'content' => $content,
            'page' => $page,
            'seo' => $this->getSeoMetadata($page),
            'structured_data' => $this->getStructuredData($page),
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
}
