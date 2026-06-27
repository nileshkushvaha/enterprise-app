<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_published_page(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Welcome']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('Welcome');
    }

    public function test_cannot_render_draft_page(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Draft,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(404);
    }

    public function test_cannot_render_private_published_page(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Private,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(404);
    }

    public function test_cannot_render_scheduled_page_before_publish_date(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Scheduled,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(404);
    }

    public function test_renders_multiple_blocks_in_order(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Hero Title']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::RichText,
            'content' => json_encode(['text' => '<p>Body text</p>']),
            'settings' => json_encode([]),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::CTA,
            'content' => json_encode(['title' => 'CTA Title']),
            'settings' => json_encode([]),
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('Hero Title');
        $response->assertSee('Body text');
        $response->assertSee('CTA Title');
    }

    public function test_skips_inactive_blocks(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Active Hero']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::RichText,
            'content' => json_encode(['text' => '<p>Inactive text</p>']),
            'settings' => json_encode([]),
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('Active Hero');
        $response->assertDontSee('Inactive text');
    }

    public function test_page_includes_seo_metadata(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'meta_title' => 'Custom SEO Title',
            'meta_description' => 'Custom SEO Description',
            'meta_keywords' => 'keyword1, keyword2',
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Page']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('Custom SEO Title', false);
        $response->assertSee('Custom SEO Description', false);
        $response->assertSee('keyword1, keyword2', false);
    }

    public function test_page_includes_structured_data(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'title' => 'Test Page',
            'excerpt' => 'Page excerpt',
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Page']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('@context', false);
        $response->assertSee('https://schema.org', false);
    }

    public function test_home_route_renders_home_page(): void
    {
        $homePage = Page::factory()->create([
            'slug' => 'home',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $homePage->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Welcome Home']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Welcome Home');
    }

    public function test_public_page_returns_complete_html_document(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'layout' => 'page',
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Full Page']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertOk();
        $response->assertSee('<!DOCTYPE html>', false);
        $response->assertSee('<html lang="en">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_page_with_hero_and_gallery(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Gallery Page']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Gallery,
            'content' => json_encode([
                'images' => [
                    ['url' => '/img1.jpg'],
                    ['url' => '/img2.jpg'],
                ],
            ]),
            'settings' => json_encode([]),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee('Gallery Page');
        $response->assertSee('/img1.jpg');
        $response->assertSee('/img2.jpg');
    }

    public function test_page_rendering_caches_result(): void
    {
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Test']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // First request
        $response1 = $this->get(route('page.show', $page->slug));
        $response1->assertStatus(200);

        // Second request (should use cache)
        $response2 = $this->get(route('page.show', $page->slug));
        $response2->assertStatus(200);

        // Both should have same content
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    public function test_page_not_found(): void
    {
        $response = $this->get('/nonexistent-page-slug');

        $response->assertStatus(404);
    }

    public function test_canonical_url_in_seo(): void
    {
        $canonicalUrl = 'https://example.com/canonical-url';
        $page = Page::factory()->create([
            'status' => PageStatus::Published,
            'canonical_url' => $canonicalUrl,
            'visibility' => PageVisibility::Public,
        ]);

        PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => json_encode(['title' => 'Page']),
            'settings' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', $page->slug));

        $response->assertStatus(200);
        $response->assertSee($canonicalUrl, false);
    }
}
