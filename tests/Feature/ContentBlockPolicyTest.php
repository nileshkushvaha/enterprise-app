<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Content\Models\ContentBlock;
use App\Enums\BlockType;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContentBlockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $pageAdmin;

    private User $postAdmin;

    private User $noPermUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the permissions we need.
        foreach (['pages', 'posts'] as $prefix) {
            foreach (['view', 'create', 'update', 'delete', 'restore'] as $ability) {
                Permission::firstOrCreate(['name' => "{$prefix}.{$ability}", 'guard_name' => 'web']);
            }
        }

        $this->pageAdmin = User::factory()->create();
        $this->pageAdmin->givePermissionTo(['pages.view', 'pages.create', 'pages.update', 'pages.delete', 'pages.restore']);

        $this->postAdmin = User::factory()->create();
        $this->postAdmin->givePermissionTo(['posts.view', 'posts.create', 'posts.update', 'posts.delete', 'posts.restore']);

        $this->noPermUser = User::factory()->create();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function pageBlock(): ContentBlock
    {
        $page = Page::factory()->create();

        return ContentBlock::create([
            'blockable_type' => 'page',
            'blockable_id' => $page->id,
            'block_type' => BlockType::Hero,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function postBlock(): ContentBlock
    {
        $post = Post::factory()->create();

        return ContentBlock::create([
            'blockable_type' => 'post',
            'blockable_id' => $post->id,
            'block_type' => BlockType::Hero,
            'content' => [],
            'settings' => [],
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    // ── viewAny ──────────────────────────────────────────────────────────

    public function test_view_any_allowed_with_pages_view_permission(): void
    {
        $this->assertTrue($this->pageAdmin->can('viewAny', ContentBlock::class));
    }

    public function test_view_any_allowed_with_posts_view_permission(): void
    {
        $this->assertTrue($this->postAdmin->can('viewAny', ContentBlock::class));
    }

    public function test_view_any_denied_without_permissions(): void
    {
        $this->assertFalse($this->noPermUser->can('viewAny', ContentBlock::class));
    }

    // ── create ───────────────────────────────────────────────────────────

    public function test_create_allowed_with_pages_create_permission(): void
    {
        $this->assertTrue($this->pageAdmin->can('create', ContentBlock::class));
    }

    public function test_create_allowed_with_posts_create_permission(): void
    {
        $this->assertTrue($this->postAdmin->can('create', ContentBlock::class));
    }

    public function test_create_denied_without_permissions(): void
    {
        $this->assertFalse($this->noPermUser->can('create', ContentBlock::class));
    }

    // ── update (page block) ───────────────────────────────────────────────

    public function test_update_page_block_allowed_with_pages_update_permission(): void
    {
        $block = $this->pageBlock();

        $this->assertTrue($this->pageAdmin->can('update', $block));
    }

    public function test_update_page_block_denied_for_posts_admin(): void
    {
        $block = $this->pageBlock();

        $this->assertFalse($this->postAdmin->can('update', $block));
    }

    // ── update (post block) ───────────────────────────────────────────────

    public function test_update_post_block_allowed_with_posts_update_permission(): void
    {
        $block = $this->postBlock();

        $this->assertTrue($this->postAdmin->can('update', $block));
    }

    public function test_update_post_block_denied_for_pages_admin(): void
    {
        $block = $this->postBlock();

        $this->assertFalse($this->pageAdmin->can('update', $block));
    }

    // ── delete ───────────────────────────────────────────────────────────

    public function test_delete_page_block_requires_pages_delete_permission(): void
    {
        $block = $this->pageBlock();

        $this->assertTrue($this->pageAdmin->can('delete', $block));
        $this->assertFalse($this->postAdmin->can('delete', $block));
        $this->assertFalse($this->noPermUser->can('delete', $block));
    }

    public function test_delete_post_block_requires_posts_delete_permission(): void
    {
        $block = $this->postBlock();

        $this->assertTrue($this->postAdmin->can('delete', $block));
        $this->assertFalse($this->pageAdmin->can('delete', $block));
    }

    // ── restore ──────────────────────────────────────────────────────────

    public function test_restore_page_block_requires_pages_restore_permission(): void
    {
        $block = $this->pageBlock();
        $block->delete();
        $block = ContentBlock::withTrashed()->find($block->id);

        $this->assertTrue($this->pageAdmin->can('restore', $block));
        $this->assertFalse($this->postAdmin->can('restore', $block));
    }

    // ── Morph alias compatibility ─────────────────────────────────────────

    public function test_policy_correctly_identifies_page_block_via_morph_alias(): void
    {
        $block = $this->pageBlock();

        // The stored value must be the alias 'page', not the FQCN.
        $this->assertSame('page', $block->blockable_type);
        $this->assertTrue($this->pageAdmin->can('update', $block));
    }

    public function test_policy_correctly_identifies_post_block_via_morph_alias(): void
    {
        $block = $this->postBlock();

        $this->assertSame('post', $block->blockable_type);
        $this->assertTrue($this->postAdmin->can('update', $block));
    }
}
