<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostService::class);
    }

    // ── Publish ───────────────────────────────────────────────────────────

    public function test_publish_sets_status_public_and_visibility(): void
    {
        $post = Post::factory()->draft()->create();

        $this->service->publishPost($post);

        $post->refresh();
        $this->assertSame(PageStatus::Published, $post->status);
        $this->assertSame(PageVisibility::Public, $post->visibility);
    }

    public function test_published_post_is_accessible_publicly(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'live-post']);

        $this->get(route('blog.show', $post->slug))->assertOk();
    }

    // ── Unpublish ─────────────────────────────────────────────────────────

    public function test_unpublish_reverts_post_to_draft_private(): void
    {
        $post = Post::factory()->published()->create();

        $this->service->unpublishPost($post);

        $post->refresh();
        $this->assertSame(PageStatus::Draft, $post->status);
        $this->assertSame(PageVisibility::Private, $post->visibility);
    }

    public function test_unpublished_post_returns_404(): void
    {
        $post = Post::factory()->draft()->create(['slug' => 'hidden-post']);

        $this->get(route('blog.show', $post->slug))->assertNotFound();
    }

    // ── Archive ───────────────────────────────────────────────────────────

    public function test_archive_sets_archived_status(): void
    {
        $post = Post::factory()->published()->create();

        $this->service->archivePost($post);

        $this->assertSame(PageStatus::Archived, $post->fresh()->status);
    }

    public function test_archived_post_is_not_publicly_accessible(): void
    {
        $post = Post::factory()->create([
            'slug' => 'archived-post',
            'status' => 'archived',
        ]);

        $this->get(route('blog.show', $post->slug))->assertNotFound();
    }

    // ── Soft delete and restore ───────────────────────────────────────────

    public function test_soft_delete_hides_post_from_default_queries(): void
    {
        $post = Post::factory()->create();
        $id = $post->id;

        $post->delete();

        $this->assertNull(Post::find($id));
        $this->assertNotNull(Post::withTrashed()->find($id));
        $this->assertSoftDeleted('posts', ['id' => $id]);
    }

    public function test_soft_deleted_post_returns_404(): void
    {
        $post = Post::factory()->published()->create(['slug' => 'deleted-post']);
        $post->delete();

        $this->get(route('blog.show', $post->slug))->assertNotFound();
    }

    public function test_restored_post_is_visible_again(): void
    {
        $post = Post::factory()->create();
        $id = $post->id;
        $post->delete();

        Post::withTrashed()->find($id)->restore();

        $this->assertNotNull(Post::find($id));
    }

    // ── scopePublished ────────────────────────────────────────────────────

    public function test_published_scope_excludes_draft_posts(): void
    {
        Post::factory()->draft()->create();

        $this->assertCount(0, Post::published()->get());
    }

    public function test_published_scope_includes_published_public_posts(): void
    {
        $post = Post::factory()->published()->create();

        $results = Post::published()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($post));
    }

    // ── scopeScheduled ────────────────────────────────────────────────────

    public function test_scheduled_scope_only_matches_past_published_at(): void
    {
        $due = Post::factory()->create([
            'status' => 'scheduled',
            'visibility' => 'public',
            'published_at' => now()->subMinute(),
        ]);
        Post::factory()->scheduled()->create(); // future

        $results = Post::scheduled()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($due));
    }

    // ── scopeFeatured ─────────────────────────────────────────────────────

    public function test_featured_scope_returns_only_featured_posts(): void
    {
        $featured = Post::factory()->published()->create(['featured' => true]);
        Post::factory()->published()->create(['featured' => false]);

        $results = Post::published()->featured()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($featured));
    }

    // ── Slug uniqueness ───────────────────────────────────────────────────

    public function test_post_slug_must_be_unique(): void
    {
        Post::factory()->create(['slug' => 'same-slug']);

        $this->expectException(QueryException::class);
        Post::factory()->create(['slug' => 'same-slug']);
    }
}
