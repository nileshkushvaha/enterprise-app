<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    // ── Page activity ─────────────────────────────────────────────────────

    public function test_creating_a_page_logs_created_event(): void
    {
        $page = Page::factory()->create(['title' => 'Launch Page', 'status' => 'published', 'visibility' => 'public']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'pages',
            'subject_type' => 'page',
            'subject_id' => $page->id,
            'event' => 'created',
        ]);
    }

    public function test_updating_a_page_logs_updated_event(): void
    {
        $page = Page::factory()->create();

        $page->update(['title' => 'New Title']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'pages',
            'subject_type' => 'page',
            'subject_id' => $page->id,
            'event' => 'updated',
        ]);
    }

    public function test_deleting_a_page_logs_deleted_event(): void
    {
        $page = Page::factory()->create();
        $id = $page->id;

        $page->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'pages',
            'subject_type' => 'page',
            'subject_id' => $id,
            'event' => 'deleted',
        ]);
    }

    // ── Post activity ─────────────────────────────────────────────────────

    public function test_creating_a_post_logs_created_event(): void
    {
        $post = Post::factory()->published()->create(['title' => 'First Post']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'posts',
            'subject_type' => 'post',
            'subject_id' => $post->id,
            'event' => 'created',
        ]);
    }

    public function test_updating_a_post_logs_updated_event(): void
    {
        $post = Post::factory()->create();

        $post->update(['title' => 'Updated Post Title']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'posts',
            'subject_type' => 'post',
            'subject_id' => $post->id,
            'event' => 'updated',
        ]);
    }

    public function test_publishing_a_post_logs_updated_event(): void
    {
        $post = Post::factory()->draft()->create();

        $post->publish();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'posts',
            'subject_type' => 'post',
            'subject_id' => $post->id,
            'event' => 'updated',
        ]);
    }

    // ── ContentBlock activity ─────────────────────────────────────────────

    public function test_creating_a_content_block_logs_created_event(): void
    {
        $page = Page::factory()->create();
        $block = $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'Block'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'content_blocks',
            'subject_type' => 'App\Content\Models\ContentBlock',
            'subject_id' => $block->id,
            'event' => 'created',
        ]);
    }

    public function test_updating_a_content_block_logs_updated_event(): void
    {
        $page = Page::factory()->create();
        $block = $page->blocks()->create([
            'block_type' => BlockType::RichText,
            'content' => ['text' => 'old'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $block->update(['content' => ['text' => 'new']]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'content_blocks',
            'subject_type' => 'App\Content\Models\ContentBlock',
            'subject_id' => $block->id,
            'event' => 'updated',
        ]);
    }

    public function test_deleting_a_content_block_logs_deleted_event(): void
    {
        $page = Page::factory()->create();
        $block = $page->blocks()->create([
            'block_type' => BlockType::CTA,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $id = $block->id;

        $block->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'content_blocks',
            'subject_type' => 'App\Content\Models\ContentBlock',
            'subject_id' => $id,
            'event' => 'deleted',
        ]);
    }

    // ── Activity log respects logOnlyDirty ────────────────────────────────

    public function test_touching_page_without_changing_tracked_fields_does_not_log(): void
    {
        $page = Page::factory()->create();

        $before = Activity::where('subject_id', $page->id)->count();

        // touch() only updates updated_at — excluded via dontLogIfAttributesChangedOnly
        $page->touch();

        $after = Activity::where('subject_id', $page->id)->count();

        $this->assertSame($before, $after);
    }
}
