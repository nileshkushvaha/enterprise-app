<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Content\Models\ContentBlock;
use App\Content\Services\ContentBlockService;
use App\Enums\BlockType;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBlockServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentBlockService $service;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentBlockService;
        $this->page = Page::factory()->create();
    }

    // ── createBlock ──────────────────────────────────────────────────────

    public function test_create_block_persists_with_correct_owner(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, ['title' => 'Hello']);

        $this->assertDatabaseHas('content_blocks', [
            'id' => $block->id,
            'blockable_type' => 'page',
            'blockable_id' => $this->page->id,
            'block_type' => BlockType::Hero->value,
        ]);
    }

    public function test_create_block_auto_increments_sort_order(): void
    {
        $first = $this->service->createBlock($this->page, BlockType::Hero, []);
        $second = $this->service->createBlock($this->page, BlockType::RichText, []);

        // First block: max(null)+1 = 1. Second block: max(1)+1 = 2.
        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
    }

    public function test_create_block_respects_explicit_sort_order(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, [], [], 99);

        $this->assertSame(99, $block->sort_order);
    }

    public function test_create_block_defaults_to_active(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, []);

        $this->assertTrue($block->is_active);
    }

    public function test_create_block_accepts_string_block_type(): void
    {
        $block = $this->service->createBlock($this->page, 'hero', ['title' => 'T']);

        $this->assertSame(BlockType::Hero, $block->block_type);
    }

    public function test_create_block_works_for_post_owner(): void
    {
        $post = Post::factory()->create();
        $block = $this->service->createBlock($post, BlockType::RichText, ['text' => '<p>hi</p>']);

        $this->assertDatabaseHas('content_blocks', [
            'blockable_type' => 'post',
            'blockable_id' => $post->id,
        ]);
    }

    // ── updateBlock ──────────────────────────────────────────────────────

    public function test_update_block_changes_content_and_settings(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, ['title' => 'Old']);

        $result = $this->service->updateBlock(
            $block,
            ['title' => 'New'],
            ['padding' => 'large'],
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('content_blocks', [
            'id' => $block->id,
        ]);

        $fresh = $block->fresh();
        $this->assertSame('New', $fresh->content['title']);
        $this->assertSame('large', $fresh->settings['padding']);
    }

    // ── reorderBlocks ────────────────────────────────────────────────────

    public function test_reorder_blocks_assigns_sequential_sort_orders(): void
    {
        $a = $this->service->createBlock($this->page, BlockType::Hero, []);
        $b = $this->service->createBlock($this->page, BlockType::RichText, []);
        $c = $this->service->createBlock($this->page, BlockType::CTA, []);

        // Reverse the order
        $this->service->reorderBlocks([$c->id, $b->id, $a->id]);

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    // ── moveBlock ────────────────────────────────────────────────────────

    public function test_move_block_repositions_within_owner(): void
    {
        $a = $this->service->createBlock($this->page, BlockType::Hero, []);
        $b = $this->service->createBlock($this->page, BlockType::RichText, []);
        $c = $this->service->createBlock($this->page, BlockType::CTA, []);

        // Move 'a' (position 0) to position 2 (last)
        $result = $this->service->moveBlock($a, 2);

        $this->assertTrue($result);

        $ordered = $this->page->blocks()->orderBy('sort_order')->pluck('id')->toArray();
        $this->assertSame([$b->id, $c->id, $a->id], $ordered);
    }

    public function test_move_block_returns_false_for_out_of_bounds_position(): void
    {
        $a = $this->service->createBlock($this->page, BlockType::Hero, []);

        $this->assertFalse($this->service->moveBlock($a, 5));
        $this->assertFalse($this->service->moveBlock($a, -1));
    }

    // ── duplicateBlock ───────────────────────────────────────────────────

    public function test_duplicate_block_creates_new_record_with_same_content(): void
    {
        $original = $this->service->createBlock($this->page, BlockType::Hero, ['title' => 'Orig'], ['padding' => 'md']);

        $copy = $this->service->duplicateBlock($original);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('Orig', $copy->content['title']);
        $this->assertSame('md', $copy->settings['padding']);
        $this->assertSame('page', $copy->blockable_type);
        $this->assertSame($this->page->id, $copy->blockable_id);
    }

    public function test_duplicate_block_appends_after_last_block(): void
    {
        $a = $this->service->createBlock($this->page, BlockType::Hero, []);
        $b = $this->service->createBlock($this->page, BlockType::RichText, []);

        $copy = $this->service->duplicateBlock($b);

        $this->assertSame($b->sort_order + 1, $copy->sort_order);
    }

    // ── deleteBlock / restoreBlock / forceDeleteBlock ────────────────────

    public function test_delete_block_soft_deletes(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, []);
        $id = $block->id;

        $this->assertTrue($this->service->deleteBlock($block));

        $this->assertSoftDeleted('content_blocks', ['id' => $id]);
        $this->assertNull(ContentBlock::find($id));
        $this->assertNotNull(ContentBlock::withTrashed()->find($id));
    }

    public function test_restore_block_brings_back_soft_deleted(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, []);
        $block->delete();

        $trashed = ContentBlock::withTrashed()->find($block->id);
        $this->assertTrue($this->service->restoreBlock($trashed));

        $this->assertNotNull(ContentBlock::find($block->id));
    }

    public function test_force_delete_removes_permanently(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, []);
        $id = $block->id;

        $this->service->forceDeleteBlock($block);

        $this->assertNull(ContentBlock::withTrashed()->find($id));
    }

    // ── toggleBlockActive ────────────────────────────────────────────────

    public function test_toggle_active_flips_is_active(): void
    {
        $block = $this->service->createBlock($this->page, BlockType::Hero, []);

        $this->assertTrue($block->is_active);

        $this->service->toggleBlockActive($block);
        $this->assertFalse($block->fresh()->is_active);

        $this->service->toggleBlockActive($block->fresh());
        $this->assertTrue($block->fresh()->is_active);
    }

    // ── getBlocks ────────────────────────────────────────────────────────

    public function test_get_blocks_returns_all_ordered_by_sort_order(): void
    {
        $this->service->createBlock($this->page, BlockType::CTA, [], [], 2);
        $this->service->createBlock($this->page, BlockType::Hero, [], [], 0);
        $this->service->createBlock($this->page, BlockType::RichText, [], [], 1);

        $blocks = $this->service->getBlocks($this->page);

        $this->assertCount(3, $blocks);
        $this->assertEquals(BlockType::Hero, $blocks[0]->block_type);
        $this->assertEquals(BlockType::RichText, $blocks[1]->block_type);
        $this->assertEquals(BlockType::CTA, $blocks[2]->block_type);
    }

    public function test_get_blocks_filters_inactive_when_requested(): void
    {
        $this->service->createBlock($this->page, BlockType::Hero, []);
        $inactive = $this->service->createBlock($this->page, BlockType::RichText, []);
        $inactive->update(['is_active' => false]);

        $active = $this->service->getBlocks($this->page, activeOnly: true);

        $this->assertCount(1, $active);
        $this->assertEquals(BlockType::Hero, $active->first()->block_type);
    }

    // ── getDefaultContent / getDefaultSettings ───────────────────────────

    public function test_get_default_content_returns_array_for_every_block_type(): void
    {
        foreach (BlockType::cases() as $type) {
            $defaults = $this->service->getDefaultContent($type);
            $this->assertIsArray($defaults, "getDefaultContent({$type->value}) must return array");
        }
    }

    public function test_get_default_settings_returns_standard_keys(): void
    {
        $settings = $this->service->getDefaultSettings(BlockType::Hero);

        $this->assertArrayHasKey('padding', $settings);
        $this->assertArrayHasKey('text_alignment', $settings);
        $this->assertArrayHasKey('container_width', $settings);
    }
}
