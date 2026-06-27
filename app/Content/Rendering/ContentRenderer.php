<?php

declare(strict_types=1);

namespace App\Content\Rendering;

use App\Content\Contracts\HasContentBlocks;
use App\Content\SEO\SeoManager;
use App\Models\Page;
use App\Models\Post;
use App\Services\BlockRenderer;
use App\Settings\GeneralSettings;
use Illuminate\Support\Collection;

/**
 * Unified content renderer — superset of PageRenderService.
 *
 * Replaces the duplicated Page/Post rendering in PageRenderService with a
 * single, owner-agnostic pipeline. All PageRenderService method signatures
 * are preserved so that zero external callsites need to change; the old
 * PageRenderService class simply extends this one.
 */
class ContentRenderer
{
    public function __construct(
        private readonly BlockRenderer $blockRenderer,
        private readonly SeoManager $seoManager,
        private readonly GeneralSettings $generalSettings,
    ) {}

    // ── Generalised API (new, for any HasContentBlocks owner) ────────────

    /**
     * Render any content type, with caching.
     */
    public function renderContent(HasContentBlocks $owner): string
    {
        return cache()->remember(
            $this->contentCacheKey($owner),
            now()->addSeconds($this->renderTtl()),
            fn () => $this->renderContentUncached($owner)
        );
    }

    /**
     * Render without caching (for admin preview).
     */
    public function renderContentPreview(HasContentBlocks $owner): string
    {
        return $this->renderContentUncached($owner);
    }

    /**
     * Flush the render cache for any owner.
     */
    public function invalidateContentCache(HasContentBlocks $owner): void
    {
        cache()->forget($this->contentCacheKey($owner));
    }

    // ── Backward-compatible Page API (mirrors PageRenderService exactly) ─

    public function render(Page $page): string
    {
        return $this->renderContent($page);
    }

    public function renderPreview(Page $page): string
    {
        return $this->renderContentPreview($page);
    }

    public function invalidateCache(?Page $page): void
    {
        if ($page) {
            $this->invalidateContentCache($page);
        }
    }

    public function getSeoMetadata(Page $page): array
    {
        return $this->seoManager->getPageMetadata($page);
    }

    public function getStructuredData(Page $page): array
    {
        return $this->seoManager->getPageStructuredData($page);
    }

    // ── Backward-compatible Post API (mirrors PageRenderService exactly) ─

    public function renderPost(Post $post): string
    {
        return $this->renderContent($post);
    }

    public function renderPostPreview(Post $post): string
    {
        return $this->renderContentPreview($post);
    }

    public function invalidatePostCache(?Post $post): void
    {
        if ($post) {
            $this->invalidateContentCache($post);
        }
    }

    public function getPostSeoMetadata(Post $post): array
    {
        return $this->seoManager->getPostMetadata($post);
    }

    public function getPostStructuredData(Post $post): array
    {
        return $this->seoManager->getPostStructuredData($post);
    }

    // ── Core rendering pipeline ──────────────────────────────────────────

    private function renderContentUncached(HasContentBlocks $owner): string
    {
        $blocksHtml = $this->renderBlocks($owner->blocks);
        $seo        = $this->resolveSeo($owner);
        $structured = $this->resolveStructuredData($owner);

        return $this->applyLayout($blocksHtml, $owner, $seo, $structured);
    }

    private function renderBlocks(Collection $blocks): string
    {
        if ($blocks->isEmpty()) {
            return '';
        }

        return $blocks
            ->sortBy('sort_order')
            ->where('is_active', true)
            ->map(fn ($block) => $this->renderSingleBlock($block))
            ->join("\n");
    }

    private function renderSingleBlock(object $block): string
    {
        try {
            return $this->blockRenderer->render($block);
        } catch (\Exception $e) {
            $blockType = $block->block_type->value ?? (string) ($block->block_type ?? 'unknown');

            return $this->renderBlockError($blockType, $e->getMessage());
        }
    }

    private function applyLayout(string $content, object $contentModel, array $seo, array $structuredData): string
    {
        $layoutName = $contentModel->layout ?? 'default';
        $layout     = match ($layoutName) {
            'landing' => 'layouts.landing',
            'blank'   => 'layouts.blank',
            default   => 'layouts.page',
        };

        return view($layout, [
            'content'         => $content,
            'page'            => $contentModel,
            'post'            => $contentModel instanceof Post ? $contentModel : null,
            'seo'             => $seo,
            'structured_data' => $structuredData,
            'site'            => $this->getSiteMetadata(),
        ])->render();
    }

    private function resolveSeo(HasContentBlocks $owner): array
    {
        return match (true) {
            $owner instanceof Post => $this->seoManager->getPostMetadata($owner),
            $owner instanceof Page => $this->seoManager->getPageMetadata($owner),
            default                => [],
        };
    }

    private function resolveStructuredData(HasContentBlocks $owner): array
    {
        return match (true) {
            $owner instanceof Post => $this->seoManager->getPostStructuredData($owner),
            $owner instanceof Page => $this->seoManager->getPageStructuredData($owner),
            default                => [],
        };
    }

    private function contentCacheKey(HasContentBlocks $owner): string
    {
        $type = class_basename($owner);
        $id   = method_exists($owner, 'getKey') ? $owner->getKey() : spl_object_id($owner);

        return strtolower("{$type}-render:{$id}");
    }

    private function renderTtl(): int
    {
        return max(1, (int) config('cms.cache.page_render_ttl', 3600));
    }

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

    private function getSiteMetadata(): array
    {
        $general = $this->generalSettings;

        if (! $general) {
            return [
                'app_name'          => config('app.name'),
                'logo'              => null,
                'favicon'           => null,
                'footer_text'       => null,
                'footer_copyright'  => null,
            ];
        }

        return [
            'app_name'         => $general->app_name,
            'logo'             => $general->logo,
            'favicon'          => $general->favicon,
            'footer_text'      => $general->footer_text,
            'footer_copyright' => $general->footer_copyright,
        ];
    }
}
