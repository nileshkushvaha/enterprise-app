<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Content\Models\ContentBlock;
use App\Content\Rendering\ContentRenderer;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that every frontend route flows through the consolidated
 * rendering pipeline and that Content Blocks appear in the output.
 */
class RenderingPipelineTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    private function addBlock(Page|Post $owner, BlockType $type, array $content, int $order = 1): ContentBlock
    {
        $morphAlias = $owner instanceof Post ? 'post' : 'page';

        return ContentBlock::create([
            'blockable_type' => $morphAlias,
            'blockable_id' => $owner->id,
            'block_type' => $type,
            'content' => json_encode($content),
            'settings' => json_encode([]),
            'sort_order' => $order,
            'is_active' => true,
        ]);
    }

    // ── Page → ContentRenderer → blocks rendered ──────────────────────────

    public function test_published_page_renders_its_content_blocks(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'slug' => 'render-test-page',
        ]);

        $this->addBlock($page, BlockType::RichText, ['text' => 'Hello from a Rich Text block']);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertOk();
        $response->assertSee('Hello from a Rich Text block');
    }

    public function test_page_with_hero_block_renders_hero_title(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'title' => 'Hero Page',
            'slug' => 'hero-page',
        ]);

        $this->addBlock($page, BlockType::Hero, ['title' => 'Welcome to our Site']);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertOk();
        $response->assertSee('Welcome to our Site');
    }

    public function test_page_without_blocks_still_renders(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'slug' => 'empty-page',
        ]);

        $this->get(route('page.show', $page->slug))->assertOk();
    }

    public function test_page_seo_title_appears_in_rendered_output(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'title' => 'SEO Test Page',
            'slug' => 'seo-test',
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertOk();
        $response->assertSee('SEO Test Page');
    }

    // ── Post → ContentRenderer → blocks rendered ──────────────────────────

    public function test_published_post_renders_its_content_blocks(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'render-test-post']);

        $this->addBlock($post, BlockType::RichText, ['text' => 'Post block content here']);

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertOk();
        $response->assertSee('Post block content here');
    }

    public function test_post_with_multiple_blocks_renders_all_in_order(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'multi-block-post']);

        $this->addBlock($post, BlockType::RichText, ['text' => 'Block One'], 1);
        $this->addBlock($post, BlockType::RichText, ['text' => 'Block Two'], 2);

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertOk();
        $response->assertSee('Block One', false);
        $response->assertSee('Block Two', false);

        // Verify order: Block One appears before Block Two
        $body = $response->getContent();
        $this->assertLessThan(strpos($body, 'Block Two'), strpos($body, 'Block One'));
    }

    public function test_inactive_block_is_not_rendered(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'slug' => 'inactive-block-page',
        ]);

        ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id' => $page->id,
            'block_type' => BlockType::RichText,
            'content' => json_encode(['text' => 'Invisible Block']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $this->get(route('page.show', $page->slug))
            ->assertOk()
            ->assertDontSee('Invisible Block', false);
    }

    // ── ContentRenderer goes through layouts/page.blade.php ───────────────

    public function test_cms_page_response_is_full_html_document(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'slug' => 'html-doc-check',
        ]);

        $content = $this->get(route('page.show', $page->slug))->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('</html>', $content);
        $this->assertStringContainsString('<main', $content);
    }

    public function test_cms_post_response_is_full_html_document(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'html-post-check']);

        $content = $this->get(route('blog.show', $post->slug))->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('</html>', $content);
    }

    // ── Blog listing / category / tag / search use layouts/frontend ────────

    public function test_blog_index_uses_frontend_layout(): void
    {
        $content = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        // frontend layout loads Inter font
        $this->assertStringContainsString('Inter', $content);
    }

    public function test_category_archive_uses_frontend_layout(): void
    {
        $cat = PostCategory::factory()->create(['is_active' => true]);

        $content = $this->get(route('blog.category', $cat->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('Inter', $content);
    }

    public function test_tag_archive_uses_frontend_layout(): void
    {
        $tag = Tag::factory()->create(['is_active' => true]);

        $content = $this->get(route('blog.tag', $tag->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('Inter', $content);
    }

    public function test_search_uses_frontend_layout(): void
    {
        $content = $this->get(route('search.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('Inter', $content);
    }

    public function test_404_error_page_uses_frontend_layout(): void
    {
        $response = $this->get('/this-does-not-exist-xyz123');

        $response->assertNotFound();
        $content = $response->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('Inter', $content);
    }

    // ── Shared layout structural consistency ──────────────────────────────

    public function test_frontend_layout_pages_all_have_main_element(): void
    {
        $cat = PostCategory::factory()->create(['is_active' => true]);
        $tag = Tag::factory()->create(['is_active' => true]);

        $routes = [
            route('blog.index'),
            route('blog.category', $cat->slug),
            route('blog.tag', $tag->slug),
            route('search.index'),
        ];

        foreach ($routes as $url) {
            $content = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<main', $content, "Missing <main> on {$url}");
        }
    }

    // ── ContentRenderer cache invalidation ────────────────────────────────

    public function test_render_cache_is_invalidated_when_page_updated(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'slug' => 'cache-test-page',
        ]);

        $this->addBlock($page, BlockType::RichText, ['text' => 'Original Content']);

        // Warm the cache
        $this->get(route('page.show', $page->slug))->assertSee('Original Content');

        // Update the block
        ContentBlock::where('blockable_id', $page->id)->update([
            'content' => json_encode(['text' => 'Updated Content']),
        ]);

        // Manually invalidate (simulates what the observer does)
        app(ContentRenderer::class)->invalidateCache($page);

        $this->get(route('page.show', $page->slug))->assertSee('Updated Content');
    }
}
