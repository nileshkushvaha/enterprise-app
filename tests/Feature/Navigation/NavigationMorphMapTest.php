<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLinkType;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationMorphMapTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): NavigationMenu
    {
        return NavigationMenu::factory()->create(['status' => NavigationStatus::Published->value]);
    }

    private function item(NavigationMenu $menu, string $linkType, string $morphType, string $morphId): NavigationItem
    {
        return NavigationItem::factory()->create([
            'navigation_id' => $menu->id,
            'link_type'     => $linkType,
            'linkable_type' => $morphType,
            'linkable_id'   => $morphId,
            'is_active'     => true,
        ]);
    }

    // ── Morph map contains navigation-relevant aliases ────────────────────

    public function test_morph_map_contains_category_and_tag(): void
    {
        $map = Relation::morphMap();

        $this->assertArrayHasKey('category', $map);
        $this->assertArrayHasKey('tag', $map);
        $this->assertSame(PostCategory::class, $map['category']);
        $this->assertSame(Tag::class, $map['tag']);
    }

    // ── Page linkable ─────────────────────────────────────────────────────

    public function test_navigation_item_linkable_resolves_to_page(): void
    {
        $page    = Page::factory()->create();
        $navItem = $this->item($this->menu(), NavigationLinkType::Page->value, 'page', $page->id);

        $resolved = $navItem->fresh()->linkable;

        $this->assertInstanceOf(Page::class, $resolved);
        $this->assertTrue($resolved->is($page));
    }

    public function test_page_morph_alias_stored_in_db(): void
    {
        $page    = Page::factory()->create();
        $navItem = $this->item($this->menu(), NavigationLinkType::Page->value, 'page', $page->id);

        $raw = \DB::table('navigation_items')->where('id', $navItem->id)->value('linkable_type');

        $this->assertSame('page', $raw);
    }

    // ── Post linkable ─────────────────────────────────────────────────────

    public function test_navigation_item_linkable_resolves_to_post(): void
    {
        $post    = Post::factory()->create();
        $navItem = $this->item($this->menu(), NavigationLinkType::Post->value, 'post', $post->id);

        $resolved = $navItem->fresh()->linkable;

        $this->assertInstanceOf(Post::class, $resolved);
        $this->assertTrue($resolved->is($post));
    }

    // ── PostCategory linkable ─────────────────────────────────────────────

    public function test_navigation_item_linkable_resolves_to_category(): void
    {
        $category = PostCategory::factory()->create();
        $navItem  = $this->item($this->menu(), NavigationLinkType::Category->value, 'category', $category->id);

        $resolved = $navItem->fresh()->linkable;

        $this->assertInstanceOf(PostCategory::class, $resolved);
        $this->assertTrue($resolved->is($category));
    }

    public function test_category_morph_alias_stored_in_db(): void
    {
        $category = PostCategory::factory()->create();
        $navItem  = $this->item($this->menu(), NavigationLinkType::Category->value, 'category', $category->id);

        $raw = \DB::table('navigation_items')->where('id', $navItem->id)->value('linkable_type');

        $this->assertSame('category', $raw);
    }

    // ── Tag linkable ──────────────────────────────────────────────────────

    public function test_navigation_item_linkable_resolves_to_tag(): void
    {
        $tag     = Tag::factory()->create();
        $navItem = $this->item($this->menu(), NavigationLinkType::Tag->value, 'tag', $tag->id);

        $resolved = $navItem->fresh()->linkable;

        $this->assertInstanceOf(Tag::class, $resolved);
        $this->assertTrue($resolved->is($tag));
    }

    public function test_tag_morph_alias_stored_in_db(): void
    {
        $tag     = Tag::factory()->create();
        $navItem = $this->item($this->menu(), NavigationLinkType::Tag->value, 'tag', $tag->id);

        $raw = \DB::table('navigation_items')->where('id', $navItem->id)->value('linkable_type');

        $this->assertSame('tag', $raw);
    }
}
