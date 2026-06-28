<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\Services\NavigationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationRendererTest extends TestCase
{
    use RefreshDatabase;

    private NavigationRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = app(NavigationRenderer::class);
    }

    private function publishedMenu(array $attrs = []): NavigationMenu
    {
        $menu = NavigationMenu::factory()->create(array_merge([
            'status'      => NavigationStatus::Published->value,
            'location'    => NavigationLocation::Header->value,
            'layout_type' => NavigationLayoutType::Standard->value,
        ], $attrs));

        // Load fresh with items relation
        return $menu->load(['items.linkable', 'items.roles', 'items.permissions']);
    }

    private function addItem(NavigationMenu $menu, array $attrs = []): NavigationItem
    {
        return NavigationItem::factory()->create(array_merge([
            'navigation_id' => $menu->id,
            'is_active'     => true,
        ], $attrs));
    }

    public function test_render_returns_navigation_tree_dto(): void
    {
        $menu = $this->publishedMenu();

        $tree = $this->renderer->render($menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']));

        $this->assertInstanceOf(NavigationTree::class, $tree);
    }

    public function test_render_maps_menu_metadata(): void
    {
        $menu = $this->publishedMenu(['name' => 'My Nav', 'slug' => 'my-nav']);
        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);

        $tree = $this->renderer->render($menu);

        $this->assertSame($menu->id, $tree->id);
        $this->assertSame('My Nav', $tree->name);
        $this->assertSame('my-nav', $tree->slug);
        $this->assertSame(NavigationLocation::Header, $tree->location);
        $this->assertSame(NavigationLayoutType::Standard, $tree->layoutType);
    }

    public function test_render_produces_empty_tree_for_menu_with_no_items(): void
    {
        $menu = $this->publishedMenu();
        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);

        $tree = $this->renderer->render($menu);

        $this->assertTrue($tree->isEmpty());
        $this->assertSame(0, $tree->totalNodes);
    }

    public function test_render_flat_items_become_root_nodes(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, ['label' => 'Home', 'url' => '/', 'link_type' => 'url']);
        $this->addItem($menu, ['label' => 'About', 'url' => '/about', 'link_type' => 'url']);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);

        $this->assertCount(2, $tree->nodes);
        $this->assertSame('Home', $tree->nodes[0]->label);
        $this->assertSame('About', $tree->nodes[1]->label);
    }

    public function test_render_builds_nested_tree_structure(): void
    {
        $menu = $this->publishedMenu();
        $parent = $this->addItem($menu, ['label' => 'Services', 'link_type' => 'url', 'url' => '/services']);
        $child  = $this->addItem($menu, ['label' => 'Web Design', 'link_type' => 'url', 'url' => '/services/web', 'parent_id' => $parent->id]);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);

        $this->assertCount(1, $tree->nodes);
        $this->assertCount(1, $tree->nodes[0]->children);
        $this->assertSame('Web Design', $tree->nodes[0]->children[0]->label);
    }

    public function test_render_sets_correct_depth_on_nested_items(): void
    {
        $menu   = $this->publishedMenu();
        $parent = $this->addItem($menu, ['label' => 'L0', 'link_type' => 'url', 'url' => '/l0']);
        $child  = $this->addItem($menu, ['label' => 'L1', 'link_type' => 'url', 'url' => '/l1', 'parent_id' => $parent->id]);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);

        $this->assertSame(0, $tree->nodes[0]->depth);
        $this->assertSame(1, $tree->nodes[0]->children[0]->depth);
    }

    public function test_render_sets_active_false_on_all_nodes(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, ['link_type' => 'url', 'url' => '/']);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);

        $this->assertFalse($tree->nodes[0]->isActive);
        $this->assertFalse($tree->nodes[0]->isAncestorActive);
    }

    public function test_render_maps_node_display_fields(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, [
            'label'      => 'Contact',
            'link_type'  => 'url',
            'url'        => '/contact',
            'icon'       => 'heroicon-o-phone',
            'css_class'  => 'nav-contact',
            'css_id'     => 'contact-link',
            'badge_text' => 'New',
            'badge_color'=> 'success',
        ]);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);
        $node = $tree->nodes[0];

        $this->assertSame('heroicon-o-phone', $node->icon);
        $this->assertSame('nav-contact', $node->cssClass);
        $this->assertSame('contact-link', $node->cssId);
        $this->assertSame('New', $node->badgeText);
        $this->assertSame('success', $node->badgeColor);
    }

    public function test_render_total_nodes_counts_all_items(): void
    {
        $menu   = $this->publishedMenu();
        $parent = $this->addItem($menu, ['link_type' => 'url', 'url' => '/a']);
        $this->addItem($menu, ['link_type' => 'url', 'url' => '/b', 'parent_id' => $parent->id]);
        $this->addItem($menu, ['link_type' => 'url', 'url' => '/c']);

        $menu = $menu->fresh()->load(['items.linkable', 'items.roles', 'items.permissions']);
        $tree = $this->renderer->render($menu);

        $this->assertSame(3, $tree->totalNodes);
    }
}
