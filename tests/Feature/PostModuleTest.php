<?php

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_published_post(): void
    {
        $post = Post::factory()->published()->create();

        ContentBlock::create([
            'blockable_type' => 'post',
            'blockable_id' => $post->id,
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'Published Post Hero'],
            'settings' => [],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertOk();
        $response->assertSee('Published Post Hero');
    }

    public function test_draft_post_is_not_publicly_accessible(): void
    {
        $post = Post::factory()->draft()->create();

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertNotFound();
    }

    public function test_blog_index_lists_only_published_posts(): void
    {
        $published = Post::factory()->published()->create(['title' => 'Visible Post']);
        Post::factory()->create([
            'title' => 'Hidden Post',
            'status' => PageStatus::Draft,
            'visibility' => PageVisibility::Private,
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertSee($published->title);
        $response->assertDontSee('Hidden Post');
    }

    public function test_reading_time_updates_from_rendered_blocks(): void
    {
        $post = Post::factory()->published()->create(['reading_time' => 1]);
        $longText = implode(' ', array_fill(0, 450, 'word'));

        ContentBlock::create([
            'blockable_type' => 'post',
            'blockable_id' => $post->id,
            'block_type' => BlockType::RichText,
            'content' => ['text' => $longText],
            'settings' => [],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertGreaterThanOrEqual(2, $post->fresh()->reading_time);
    }

    public function test_sitemap_contains_published_blog_posts(): void
    {
        $post = Post::factory()->published()->create();

        $response = $this->get(route('seo.sitemap'));

        $response->assertOk();
        $response->assertSee(route('blog.show', $post->slug), false);
    }

    public function test_can_attach_related_posts_and_duplicate_them(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Primary Post']);
        $related = Post::factory()->published()->create(['title' => 'Related Post']);

        $post->relatedPosts()->sync([$related->id]);

        $this->assertTrue($post->relatedPosts()->whereKey($related->id)->exists());

        $duplicate = app(PostService::class)->duplicatePost($post);

        $this->assertTrue($duplicate->relatedPosts()->whereKey($related->id)->exists());
    }

    public function test_related_posts_service_returns_manual_related_posts_first(): void
    {
        $post = Post::factory()->published()->create();
        $related = Post::factory()->published()->create();

        $post->relatedPosts()->sync([$related->id]);

        $results = app(PostService::class)->getRelatedPosts($post);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($related));
    }
}
