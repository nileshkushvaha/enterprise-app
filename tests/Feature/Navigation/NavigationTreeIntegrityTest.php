<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class NavigationTreeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): NavigationMenu
    {
        return NavigationMenu::factory()->create(['status' => NavigationStatus::Published->value]);
    }

    private function item(NavigationMenu $menu, ?string $parentId = null, array $attrs = []): NavigationItem
    {
        return NavigationItem::factory()->create(array_merge([
            'navigation_id' => $menu->id,
            'parent_id'     => $parentId,
            'link_type'     => 'url',
            'url'           => '/item-' . uniqid(),
            'is_active'     => true,
        ], $attrs));
    }

    // ── UUID primary keys ─────────────────────────────────────────────────

    public function test_navigation_menu_uses_uuid_primary_key(): void
    {
        $menu = $this->menu();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $menu->id,
        );
    }

    public function test_navigation_item_uses_uuid_primary_key(): void
    {
        $item = $this->item($this->menu());

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $item->id,
        );
    }

    // ── NestedSet columns ─────────────────────────────────────────────────

    public function test_nestedset_columns_are_populated_on_insert(): void
    {
        $item = $this->item($this->menu());
        $item->refresh();

        $this->assertGreaterThan(0, $item->_lft);
        $this->assertGreaterThan($item->_lft, $item->_rgt);
    }

    public function test_child_lft_rgt_is_within_parent_bounds(): void
    {
        $menu   = $this->menu();
        $parent = $this->item($menu);
        $child  = $this->item($menu, $parent->id);

        $parent->refresh();
        $child->refresh();

        $this->assertGreaterThan($parent->_lft, $child->_lft);
        $this->assertLessThan($parent->_rgt, $child->_rgt);
    }

    public function test_siblings_have_non_overlapping_lft_rgt(): void
    {
        $menu   = $this->menu();
        $sibA   = $this->item($menu);
        $sibB   = $this->item($menu);

        $sibA->refresh();
        $sibB->refresh();

        // Siblings must not overlap
        $this->assertTrue(
            $sibA->_rgt < $sibB->_lft || $sibB->_rgt < $sibA->_lft,
            "Siblings have overlapping _lft/_rgt ranges.",
        );
    }

    // ── Soft deletes ──────────────────────────────────────────────────────

    public function test_deleted_item_excluded_from_default_query(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $item->delete();

        $this->assertDatabaseMissing('navigation_items', [
            'id'         => $item->id,
            'deleted_at' => null,
        ]);

        $this->assertSame(0, NavigationItem::where('navigation_id', $menu->id)->count());
    }

    public function test_deleted_item_visible_with_trashed(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $item->delete();

        $this->assertNotNull(NavigationItem::withTrashed()->find($item->id));
    }

    public function test_restored_item_appears_in_default_query(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $item->delete();
        $item->restore();

        $this->assertSame(1, NavigationItem::where('navigation_id', $menu->id)->count());
    }

    // ── Circular reference prevention ─────────────────────────────────────

    public function test_self_reference_throws_logic_exception(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->expectException(LogicException::class);

        $item->parent_id = $item->id;
        $item->save();
    }

    public function test_descendant_as_parent_throws_logic_exception(): void
    {
        $menu   = $this->menu();
        $parent = $this->item($menu);
        $child  = $this->item($menu, $parent->id);

        $parent->refresh();
        $child->refresh();

        $this->expectException(LogicException::class);

        // Try to make the child the parent of its own parent
        $parent->parent_id = $child->id;
        $parent->save();
    }

    // ── Activity log ──────────────────────────────────────────────────────

    public function test_navigation_menu_logs_activity_on_create(): void
    {
        $menu = NavigationMenu::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => NavigationMenu::class,
            'subject_id'   => $menu->id,
            'log_name'     => 'navigation',
        ]);
    }

    public function test_navigation_item_logs_activity_on_create(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => NavigationItem::class,
            'subject_id'   => $item->id,
            'log_name'     => 'navigation_item',
        ]);
    }
}
