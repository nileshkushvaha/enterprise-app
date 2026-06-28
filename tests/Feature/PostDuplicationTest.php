<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostService::class);
    }

    // ── Basic duplication ────────────────────────────────────────────────

    public function test_duplicate_post_creates_new_record(): void
    {
        $original = Post::factory()->published()->create(['title' => 'Original Post']);

        $copy = $this->service->duplicatePost($original);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertDatabaseCount('posts', 2);
    }

    public function test_duplicate_post_title_gets_copy_suffix(): void
    {
        $original = Post::factory()->create(['title' => 'My Post']);

        $copy = $this->service->duplicatePost($original);

        $this->assertSame('My Post (Copy)', $copy->title);
    }

    public function test_duplicate_post_starts_as_draft_private(): void
    {
        $original = Post::factory()->published()->create();

        $copy = $this->service->duplicatePost($original);

        $this->assertSame(PageStatus::Draft, $copy->status);
        $this->assertSame(PageVisibility::Private, $copy->visibility);
        $this->assertNull($copy->published_at);
        $this->assertFalse($copy->featured);
    }

    // ── Slug uniqueness ──────────────────────────────────────────────────

    public function test_duplicate_post_generates_unique_slug(): void
    {
        $original = Post::factory()->create(['slug' => 'my-article', 'title' => 'My Article']);

        $copy = $this->service->duplicatePost($original);

        $this->assertSame('my-article-copy', $copy->slug);
    }

    public function test_duplicate_post_slug_increments_when_copy_slug_taken(): void
    {
        $original = Post::factory()->create(['slug' => 'hello-world']);
        Post::factory()->create(['slug' => 'hello-world-copy']);

        $copy = $this->service->duplicatePost($original);

        $this->assertSame('hello-world-copy-1', $copy->slug);
    }

    // ── Block duplication ────────────────────────────────────────────────

    public function test_duplicate_post_copies_all_content_blocks(): void
    {
        $original = Post::factory()->create();
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero,    'content' => ['title' => 'H'], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::RichText, 'content' => ['text' => 'T'], 'settings' => [], 'sort_order' => 1, 'is_active' => true]);

        $copy = $this->service->duplicatePost($original);

        // fresh() bypasses any relationship cache set by ContentBlockObserver during duplication
        $this->assertCount(2, $copy->fresh()->blocks);
    }

    public function test_duplicate_post_blocks_have_new_ids(): void
    {
        $original = Post::factory()->create();
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $copy = $this->service->duplicatePost($original);

        $this->assertNotSame(
            $original->fresh()->blocks->first()->id,
            $copy->fresh()->blocks->first()->id,
        );
    }

    public function test_duplicate_post_blocks_are_owned_by_new_post(): void
    {
        $original = Post::factory()->create();
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::CTA, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $copy = $this->service->duplicatePost($original);

        $copiedBlock = $copy->fresh()->blocks->first();
        $this->assertSame($copy->id, $copiedBlock->blockable_id);
        $this->assertSame('post', $copiedBlock->blockable_type);
    }

    // ── Taxonomy duplication ─────────────────────────────────────────────

    public function test_duplicate_post_copies_categories(): void
    {
        $original = Post::factory()->create();
        $cat = PostCategory::factory()->create();
        $original->categories()->sync([$cat->id]);

        $copy = $this->service->duplicatePost($original);

        $this->assertTrue($copy->categories()->whereKey($cat->id)->exists());
    }

    public function test_duplicate_post_copies_tags(): void
    {
        $original = Post::factory()->create();
        $tag = Tag::factory()->create();
        $original->tags()->sync([$tag->id]);

        $copy = $this->service->duplicatePost($original);

        $this->assertTrue($copy->tags()->whereKey($tag->id)->exists());
    }

    public function test_duplicate_post_copies_related_posts(): void
    {
        $original = Post::factory()->published()->create();
        $related = Post::factory()->published()->create();
        $original->relatedPosts()->sync([$related->id]);

        $copy = $this->service->duplicatePost($original);

        $this->assertTrue($copy->relatedPosts()->whereKey($related->id)->exists());
    }

    // ── Reading time ──────────────────────────────────────────────────────

    public function test_duplicate_post_recalculates_reading_time(): void
    {
        $original = Post::factory()->create(['reading_time' => 99]);
        $longText = implode(' ', array_fill(0, 600, 'word'));
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::RichText, 'content' => ['text' => $longText], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $copy = $this->service->duplicatePost($original);

        // 600 words / 200 wpm = 3 minutes; reading_time on the copy must not be the original's 99.
        // Use fresh() to bypass any relationship cache set by the observer during the loop.
        $this->assertNotSame(99, $copy->fresh()->reading_time);
    }

    // ── Original unchanged ───────────────────────────────────────────────

    public function test_original_post_is_unchanged_after_duplication(): void
    {
        $original = Post::factory()->published()->create(['title' => 'Original']);
        ContentBlock::create(['blockable_type' => 'post', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $this->service->duplicatePost($original);

        $original->refresh();
        $this->assertSame('Original', $original->title);
        $this->assertSame(PageStatus::Published, $original->status);
        $this->assertCount(1, $original->blocks);
    }
}
