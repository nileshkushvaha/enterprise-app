<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Rendering\ContentRenderer;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    // ── Page render cache ─────────────────────────────────────────────────

    public function test_adding_block_to_page_invalidates_render_cache(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        // Prime the cache by rendering.
        $this->get(route('page.show', $page->slug))->assertOk();

        // The cache key used by ContentRenderer.
        $cacheKey = strtolower("Page-render:{$page->id}");
        $this->assertTrue(Cache::has($cacheKey));

        // Adding a block triggers ContentBlockObserver → invalidateCache().
        $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'New Block'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_updating_block_invalidates_page_render_cache(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);
        $block = $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'Old'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Prime cache.
        $this->get(route('page.show', $page->slug))->assertOk();

        $cacheKey = strtolower("Page-render:{$page->id}");
        $this->assertTrue(Cache::has($cacheKey));

        $block->update(['content' => ['title' => 'New']]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_deleting_block_invalidates_page_render_cache(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);
        $block = $page->blocks()->create([
            'block_type' => BlockType::CTA,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->get(route('page.show', $page->slug))->assertOk();
        $cacheKey = strtolower("Page-render:{$page->id}");
        $this->assertTrue(Cache::has($cacheKey));

        $block->delete();

        $this->assertFalse(Cache::has($cacheKey));
    }

    // ── Post render cache + reading time ──────────────────────────────────

    public function test_adding_block_to_post_recalculates_reading_time(): void
    {
        $post = Post::factory()->published()->create(['reading_time' => 1]);

        $longText = implode(' ', array_fill(0, 500, 'word'));
        $post->blocks()->create([
            'block_type' => BlockType::RichText,
            'content' => ['text' => $longText],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // 500 words / 200 wpm = 2.5 → ceil = 3
        $this->assertGreaterThanOrEqual(2, $post->fresh()->reading_time);
    }

    public function test_inactive_blocks_excluded_from_reading_time(): void
    {
        $post = Post::factory()->published()->create(['reading_time' => 1]);
        $longText = implode(' ', array_fill(0, 1000, 'word'));

        $post->blocks()->create([
            'block_type' => BlockType::RichText,
            'content' => ['text' => $longText],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => false,  // inactive — must be excluded
        ]);

        // Reading time should stay at 1 because the block is inactive.
        $this->assertSame(1, $post->fresh()->reading_time);
    }

    // ── Cache is rebuilt after invalidation ───────────────────────────────

    public function test_page_cache_is_rebuilt_on_next_request_after_invalidation(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);
        $block = $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'Version 1'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // First render — caches Version 1.
        $this->get(route('page.show', $page->slug))
            ->assertOk()
            ->assertSee('Version 1');

        // Update the block — cache is invalidated.
        $block->update(['content' => ['title' => 'Version 2']]);

        // Next request should render Version 2 and re-cache it.
        $this->get(route('page.show', $page->slug))
            ->assertOk()
            ->assertSee('Version 2');
    }

    // ── Direct ContentRenderer API ────────────────────────────────────────

    public function test_invalidate_content_cache_clears_key(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);
        $renderer = app(ContentRenderer::class);

        // Prime the cache.
        $renderer->render($page);
        $key = strtolower("Page-render:{$page->id}");
        $this->assertTrue(Cache::has($key));

        $renderer->invalidateCache($page);

        $this->assertFalse(Cache::has($key));
    }

    public function test_render_preview_bypasses_cache(): void
    {
        $page = Page::factory()->create(['status' => 'published', 'visibility' => 'public']);
        $renderer = app(ContentRenderer::class);

        $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'Cached Version'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Cache the "Cached Version".
        $renderer->render($page);

        // Change the block content.
        $page->blocks->first()->update(['content' => ['title' => 'Preview Version']]);

        // renderPreview must bypass the cache and show the new content.
        $preview = $renderer->renderPreview($page->fresh());

        $this->assertStringContainsString('Preview Version', $preview);
    }
}
