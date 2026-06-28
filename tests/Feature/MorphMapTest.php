<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorphMapTest extends TestCase
{
    use RefreshDatabase;

    // ── Morph Map Registration ────────────────────────────────────────────

    public function test_morph_map_contains_page_and_post_aliases(): void
    {
        $map = Relation::morphMap();

        $this->assertArrayHasKey('page', $map);
        $this->assertArrayHasKey('post', $map);
        $this->assertSame(Page::class, $map['page']);
        $this->assertSame(Post::class, $map['post']);
    }

    public function test_page_get_morph_class_returns_alias(): void
    {
        $this->assertSame('page', (new Page)->getMorphClass());
    }

    public function test_post_get_morph_class_returns_alias(): void
    {
        $this->assertSame('post', (new Post)->getMorphClass());
    }

    // ── Database Storage ──────────────────────────────────────────────────

    public function test_page_blocks_store_alias_in_blockable_type(): void
    {
        $page = Page::factory()->create();
        $block = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('content_blocks', [
            'id' => $block->id,
            'blockable_type' => 'page',
        ]);

        // Verify the raw DB value is the alias, not the FQCN.
        $raw = \DB::table('content_blocks')->where('id', $block->id)->value('blockable_type');
        $this->assertSame('page', $raw);
    }

    public function test_post_blocks_store_alias_in_blockable_type(): void
    {
        $post = Post::factory()->create();
        $block = $post->blocks()->create([
            'block_type' => BlockType::RichText,
            'content' => ['text' => 'hi'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $raw = \DB::table('content_blocks')->where('id', $block->id)->value('blockable_type');
        $this->assertSame('post', $raw);
    }

    // ── morphMany resolution ──────────────────────────────────────────────

    public function test_page_morph_many_resolves_blocks_via_alias(): void
    {
        $page = Page::factory()->create();
        $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => ['title' => 'T'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertCount(1, $page->fresh()->blocks);
    }

    public function test_post_morph_many_resolves_blocks_via_alias(): void
    {
        $post = Post::factory()->create();
        $post->blocks()->create([
            'block_type' => BlockType::CTA,
            'content' => ['title' => 'C'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertCount(1, $post->fresh()->blocks);
    }

    // ── morphTo resolution ────────────────────────────────────────────────

    public function test_content_block_morph_to_resolves_to_page(): void
    {
        $page = Page::factory()->create();
        $block = $page->blocks()->create([
            'block_type' => BlockType::Hero,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $owner = $block->fresh()->blockable;

        $this->assertInstanceOf(Page::class, $owner);
        $this->assertTrue($owner->is($page));
    }

    public function test_content_block_morph_to_resolves_to_post(): void
    {
        $post = Post::factory()->create();
        $block = $post->blocks()->create([
            'block_type' => BlockType::RichText,
            'content' => ['text' => 'body'],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $owner = $block->fresh()->blockable;

        $this->assertInstanceOf(Post::class, $owner);
        $this->assertTrue($owner->is($post));
    }

    // ── Cross-type isolation ──────────────────────────────────────────────

    public function test_page_blocks_do_not_appear_in_post_blocks_relationship(): void
    {
        $page = Page::factory()->create();
        $post = Post::factory()->create();

        $page->blocks()->create(['block_type' => BlockType::Hero, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);
        $post->blocks()->create(['block_type' => BlockType::CTA,  'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $this->assertCount(1, $page->fresh()->blocks);
        $this->assertCount(1, $post->fresh()->blocks);
        $this->assertNotSame(
            $page->fresh()->blocks->first()->id,
            $post->fresh()->blocks->first()->id,
        );
    }
}
