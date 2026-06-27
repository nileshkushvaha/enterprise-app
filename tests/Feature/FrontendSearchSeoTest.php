<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendSearchSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_only_published_public_pages(): void
    {
        Page::factory()->create([
            'title' => 'About Company',
            'slug' => 'about-company',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        Page::factory()->create([
            'title' => 'About Internal',
            'slug' => 'about-internal',
            'status' => PageStatus::Draft,
            'visibility' => PageVisibility::Private,
        ]);

        $response = $this->get(route('search.index', ['q' => 'About']));

        $response->assertOk();
        $response->assertSee('About Company');
        $response->assertDontSee('About Internal');
    }

    public function test_search_returns_published_posts_too(): void
    {
        Page::factory()->create([
            'title' => 'About Company',
            'slug' => 'about-company',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        Post::factory()->published()->create([
            'title' => 'About Blog',
            'slug' => 'about-blog',
        ]);

        $response = $this->get(route('search.index', ['q' => 'About']));

        $response->assertOk();
        $response->assertSee('About Company');
        $response->assertSee('About Blog');
    }

    public function test_search_is_not_indexed_by_robots(): void
    {
        $response = $this->get(route('search.index', ['q' => 'home']));

        $response->assertOk();
        $response->assertSee('noindex, follow', false);
    }

    public function test_sitemap_includes_only_published_public_pages(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        Page::factory()->create([
            'slug' => 'about-us',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        Page::factory()->create([
            'slug' => 'secret',
            'status' => PageStatus::Draft,
            'visibility' => PageVisibility::Private,
        ]);

        $response = $this->get(route('seo.sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('home'), false);
        $response->assertSee(route('page.show', 'about-us'), false);
        $response->assertDontSee(route('page.show', 'secret'), false);
    }

    public function test_robots_txt_contains_sitemap_and_admin_disallow(): void
    {
        $response = $this->get(route('seo.robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Sitemap: ' . route('seo.sitemap'), false);
    }

    public function test_search_cache_is_invalidated_when_new_page_is_created(): void
    {
        $this->get(route('search.index', ['q' => 'Pricing']))->assertOk();

        Page::factory()->create([
            'title' => 'Pricing Plans',
            'slug' => 'pricing-plans',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        $response = $this->get(route('search.index', ['q' => 'Pricing']));

        $response->assertOk();
        $response->assertSee('Pricing Plans');
    }

    public function test_search_cache_is_invalidated_when_new_post_is_created(): void
    {
        $this->get(route('search.index', ['q' => 'Guide']))->assertOk();

        Post::factory()->published()->create([
            'title' => 'Guide to Architecture',
            'slug' => 'guide-to-architecture',
        ]);

        $response = $this->get(route('search.index', ['q' => 'Guide']));

        $response->assertOk();
        $response->assertSee('Guide to Architecture');
    }

    public function test_sitemap_cache_is_invalidated_when_page_is_updated(): void
    {
        $page = Page::factory()->create([
            'slug' => 'about-us',
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
        ]);

        $first = $this->get(route('seo.sitemap'));
        $first->assertOk();
        $first->assertSee($page->updated_at->toAtomString(), false);

        $this->travel(2)->seconds();
        $page->touch();
        $page->refresh();

        $second = $this->get(route('seo.sitemap'));
        $second->assertOk();
        $second->assertSee($page->updated_at->toAtomString(), false);
    }
}
