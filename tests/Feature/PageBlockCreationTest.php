<?php

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBlockCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_page_block_with_form_data(): void
    {
        $page = Page::factory()->create();

        $block = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::Hero,
            'content'        => json_encode([
                'title'        => 'Welcome',
                'subtitle'     => 'Test',
                'image'        => null,
                'button_text'  => 'Click',
                'button_link'  => '/test',
                'button_style' => 'primary',
            ]),
            'settings'   => json_encode([]),
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        $this->assertNotNull($block->id);
        $this->assertEquals(BlockType::Hero, $block->block_type);
        $this->assertEquals($page->id, $block->blockable_id);
    }

    /** @test */
    public function test_can_retrieve_page_with_blocks(): void
    {
        $page = Page::factory()->create();

        ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::Hero,
            'content'        => json_encode(['title' => 'Hero']),
            'settings'       => json_encode([]),
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::RichText,
            'content'        => json_encode(['text' => '<p>Content</p>']),
            'settings'       => json_encode([]),
            'sort_order'     => 2,
            'is_active'      => true,
        ]);

        $this->assertCount(2, $page->blocks);
        $this->assertEquals(BlockType::Hero, $page->blocks[0]->block_type);
        $this->assertEquals(BlockType::RichText, $page->blocks[1]->block_type);
    }

    /** @test */
    public function test_can_update_page_block(): void
    {
        $page = Page::factory()->create();

        $block = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::Image,
            'content'        => json_encode(['image' => '/old.jpg']),
            'settings'       => json_encode([]),
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        $block->update([
            'content' => json_encode(['image' => '/new.jpg']),
        ]);

        $updatedBlock = ContentBlock::find($block->id);
        $content = json_decode($updatedBlock->content, true);
        $this->assertEquals('/new.jpg', $content['image']);
    }

    /** @test */
    public function test_can_delete_page_block(): void
    {
        $page = Page::factory()->create();

        $block = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::CTA,
            'content'        => json_encode(['title' => 'CTA']),
            'settings'       => json_encode([]),
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        $blockId = $block->id;
        $block->delete();

        $this->assertSoftDeleted('content_blocks', ['id' => $blockId]);
    }

    /** @test */
    public function test_page_blocks_are_ordered_by_sort_order(): void
    {
        $page = Page::factory()->create();

        foreach ([
            [BlockType::Hero,     3],
            [BlockType::RichText, 1],
            [BlockType::CTA,      2],
        ] as [$type, $order]) {
            ContentBlock::create([
                'blockable_type' => 'page',
                'blockable_id'   => $page->id,
                'block_type'     => $type,
                'content'        => json_encode(['title' => 'x']),
                'settings'       => json_encode([]),
                'sort_order'     => $order,
                'is_active'      => true,
            ]);
        }

        $blocks = $page->blocks()->orderBy('sort_order')->get();

        $this->assertEquals(BlockType::RichText, $blocks[0]->block_type);
        $this->assertEquals(BlockType::CTA, $blocks[1]->block_type);
        $this->assertEquals(BlockType::Hero, $blocks[2]->block_type);
    }

    /** @test */
    public function test_page_blocks_soft_delete(): void
    {
        $page = Page::factory()->create();

        $block1 = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::Hero,
            'content'        => json_encode(['title' => 'Hero']),
            'settings'       => json_encode([]),
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::CTA,
            'content'        => json_encode(['title' => 'CTA']),
            'settings'       => json_encode([]),
            'sort_order'     => 2,
            'is_active'      => true,
        ]);

        $block1->delete();

        $this->assertCount(1, $page->blocks);
        $this->assertCount(2, $page->blocks()->withTrashed()->get());
    }

    /** @test */
    public function test_page_block_supports_all_block_types(): void
    {
        $page = Page::factory()->create();

        foreach (BlockType::cases() as $blockType) {
            $block = ContentBlock::create([
                'blockable_type' => 'page',
                'blockable_id'   => $page->id,
                'block_type'     => $blockType,
                'content'        => json_encode(['test' => 'data']),
                'settings'       => json_encode([]),
                'sort_order'     => 1,
                'is_active'      => true,
            ]);

            $this->assertEquals($blockType, $block->block_type);
        }

        $this->assertCount(
            18,
            ContentBlock::where('blockable_type', 'page')
                ->where('blockable_id', $page->id)
                ->get()
        );
    }

    /** @test */
    public function test_page_block_relationship_with_page(): void
    {
        $page = Page::factory()->create();

        $block = ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id'   => $page->id,
            'block_type'     => BlockType::Hero,
            'content'        => json_encode(['title' => 'Hero']),
            'settings'       => json_encode([]),
            'sort_order'     => 1,
            'is_active'      => true,
        ]);

        $this->assertEquals($page->id, $block->page->id);
        $this->assertEquals($page->title, $block->page->title);
    }
}
