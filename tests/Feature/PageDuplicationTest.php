<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PageService::class);
    }

    // ── Basic duplication ────────────────────────────────────────────────

    public function test_duplicate_page_creates_new_record(): void
    {
        $original = Page::factory()->create(['title' => 'Original', 'status' => 'published']);

        $copy = $this->service->duplicatePage($original);

        $this->assertNotSame($original->id, $copy->id);
        $this->assertDatabaseCount('pages', 2);
    }

    public function test_duplicate_page_title_gets_copy_suffix(): void
    {
        $original = Page::factory()->create(['title' => 'My Page']);

        $copy = $this->service->duplicatePage($original);

        $this->assertSame('My Page (Copy)', $copy->title);
    }

    public function test_duplicate_page_starts_as_draft_private(): void
    {
        $original = Page::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $copy = $this->service->duplicatePage($original);

        $this->assertSame(PageStatus::Draft, $copy->status);
        $this->assertSame(PageVisibility::Private, $copy->visibility);
        $this->assertNull($copy->published_at);
    }

    // ── Slug uniqueness ──────────────────────────────────────────────────

    public function test_duplicate_page_generates_unique_slug(): void
    {
        $original = Page::factory()->create(['slug' => 'about-us']);

        $copy = $this->service->duplicatePage($original);

        $this->assertSame('about-us-copy', $copy->slug);
    }

    public function test_duplicate_page_slug_increments_when_copy_slug_taken(): void
    {
        $original = Page::factory()->create(['slug' => 'about-us']);
        Page::factory()->create(['slug' => 'about-us-copy']);

        $copy = $this->service->duplicatePage($original);

        $this->assertSame('about-us-copy-1', $copy->slug);
    }

    public function test_duplicate_page_slug_keeps_incrementing(): void
    {
        $original = Page::factory()->create(['slug' => 'landing']);
        Page::factory()->create(['slug' => 'landing-copy']);
        Page::factory()->create(['slug' => 'landing-copy-1']);

        $copy = $this->service->duplicatePage($original);

        $this->assertSame('landing-copy-2', $copy->slug);
    }

    // ── Block duplication ────────────────────────────────────────────────

    public function test_duplicate_page_copies_all_content_blocks(): void
    {
        $original = Page::factory()->create();
        $b1 = ContentBlock::create(['blockable_type' => 'page', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero,    'content' => ['title' => 'H'], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);
        $b2 = ContentBlock::create(['blockable_type' => 'page', 'blockable_id' => $original->id, 'block_type' => BlockType::RichText, 'content' => ['text' => 'T'], 'settings' => [], 'sort_order' => 1, 'is_active' => true]);

        $copy = $this->service->duplicatePage($original);

        $this->assertCount(2, $copy->blocks);
    }

    public function test_duplicate_page_blocks_have_new_ids(): void
    {
        $original = Page::factory()->create();
        ContentBlock::create(['blockable_type' => 'page', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $copy = $this->service->duplicatePage($original);

        $originalBlockId = $original->blocks->first()->id;
        $copiedBlockId = $copy->blocks->first()->id;

        $this->assertNotSame($originalBlockId, $copiedBlockId);
    }

    public function test_duplicate_page_blocks_preserve_content_and_sort_order(): void
    {
        $original = Page::factory()->create();
        ContentBlock::create(['blockable_type' => 'page', 'blockable_id' => $original->id, 'block_type' => BlockType::CTA, 'content' => ['title' => 'Act Now'], 'settings' => ['padding' => 'lg'], 'sort_order' => 5, 'is_active' => true]);

        $copy = $this->service->duplicatePage($original);
        $copiedBlock = $copy->blocks->first();

        $this->assertSame(BlockType::CTA, $copiedBlock->block_type);
        $this->assertSame('Act Now', $copiedBlock->content['title']);
        $this->assertSame(5, $copiedBlock->sort_order);
    }

    public function test_duplicate_page_with_no_blocks_creates_empty_copy(): void
    {
        $original = Page::factory()->create();

        $copy = $this->service->duplicatePage($original);

        $this->assertCount(0, $copy->blocks);
    }

    // ── Original unchanged ───────────────────────────────────────────────

    public function test_original_page_is_unchanged_after_duplication(): void
    {
        $original = Page::factory()->create([
            'title' => 'Original',
            'status' => 'published',
        ]);
        ContentBlock::create(['blockable_type' => 'page', 'blockable_id' => $original->id, 'block_type' => BlockType::Hero, 'content' => [], 'settings' => [], 'sort_order' => 0, 'is_active' => true]);

        $this->service->duplicatePage($original);

        $original->refresh();
        $this->assertSame('Original', $original->title);
        $this->assertSame(PageStatus::Published, $original->status);
        $this->assertCount(1, $original->blocks);
    }
}
