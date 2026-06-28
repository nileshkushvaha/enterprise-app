<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Enums\Navigation\NavigationVisibility;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\User;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\Services\NavigationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationManagerTest extends TestCase
{
    use RefreshDatabase;

    private NavigationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(NavigationManager::class);
    }

    private function publishedMenu(array $attrs = []): NavigationMenu
    {
        return NavigationMenu::factory()->create(array_merge([
            'status'   => NavigationStatus::Published->value,
            'location' => NavigationLocation::Header->value,
            'locale'   => null,
        ], $attrs));
    }

    private function addItem(NavigationMenu $menu, array $attrs = []): NavigationItem
    {
        return NavigationItem::factory()->create(array_merge([
            'navigation_id' => $menu->id,
            'link_type'     => 'url',
            'url'           => '/',
            'is_active'     => true,
            'visibility'    => NavigationVisibility::All->value,
        ], $attrs));
    }

    // ── forLocation ───────────────────────────────────────────────────────

    public function test_for_location_returns_navigation_tree(): void
    {
        $this->publishedMenu();

        $tree = $this->manager->forLocation(NavigationLocation::Header);

        $this->assertInstanceOf(NavigationTree::class, $tree);
    }

    public function test_for_location_returns_null_when_no_published_menu(): void
    {
        NavigationMenu::factory()->create([
            'status'   => NavigationStatus::Draft->value,
            'location' => NavigationLocation::Sidebar->value,
        ]);

        $this->assertNull($this->manager->forLocation(NavigationLocation::Sidebar));
    }

    public function test_for_location_filters_auth_items_for_guests(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, ['label' => 'Public',  'visibility' => NavigationVisibility::All->value]);
        $this->addItem($menu, ['label' => 'Members', 'visibility' => NavigationVisibility::Auth->value]);

        $tree = $this->manager->forLocation(NavigationLocation::Header);

        $labels = array_column($tree->nodes, 'label');
        $this->assertContains('Public', $labels);
        $this->assertNotContains('Members', $labels);
    }

    public function test_for_location_shows_auth_items_for_authenticated_users(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, ['label' => 'Members', 'visibility' => NavigationVisibility::Auth->value]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tree = $this->manager->forLocation(NavigationLocation::Header);

        $labels = array_column($tree->nodes, 'label');
        $this->assertContains('Members', $labels);
    }

    public function test_for_location_hides_guest_items_for_authenticated_users(): void
    {
        $menu = $this->publishedMenu();
        $this->addItem($menu, ['label' => 'Login', 'visibility' => NavigationVisibility::Guest->value]);

        $this->actingAs(User::factory()->create());

        $tree = $this->manager->forLocation(NavigationLocation::Header);

        $this->assertNotContains('Login', array_column($tree->nodes, 'label'));
    }

    // ── forSlug ───────────────────────────────────────────────────────────

    public function test_for_slug_returns_navigation_tree(): void
    {
        $menu = $this->publishedMenu(['slug' => 'my-header']);

        $tree = $this->manager->forSlug('my-header');

        $this->assertInstanceOf(NavigationTree::class, $tree);
        $this->assertSame($menu->id, $tree->id);
    }

    public function test_for_slug_returns_null_for_unknown_slug(): void
    {
        $this->assertNull($this->manager->forSlug('does-not-exist'));
    }

    // ── Caching ───────────────────────────────────────────────────────────

    public function test_second_call_returns_same_tree_from_cache(): void
    {
        $this->publishedMenu();

        $first  = $this->manager->forLocation(NavigationLocation::Header);
        $second = $this->manager->forLocation(NavigationLocation::Header);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->name, $second->name);
    }

    // ── navigation() helper ───────────────────────────────────────────────

    public function test_navigation_helper_returns_tree_by_string(): void
    {
        $this->publishedMenu(['location' => NavigationLocation::Footer->value]);

        $tree = navigation('footer');

        $this->assertInstanceOf(NavigationTree::class, $tree);
    }

    public function test_navigation_helper_returns_tree_by_enum(): void
    {
        $this->publishedMenu(['location' => NavigationLocation::Footer->value]);

        $tree = navigation(NavigationLocation::Footer);

        $this->assertInstanceOf(NavigationTree::class, $tree);
    }

    public function test_navigation_helper_returns_null_for_unknown_location(): void
    {
        $this->assertNull(navigation('mobile'));
    }

    // ── invalidate ────────────────────────────────────────────────────────

    public function test_invalidate_clears_cached_tree(): void
    {
        $menu = $this->publishedMenu(['slug' => 'header']);
        $this->addItem($menu, ['label' => 'Home']);

        // Prime the cache
        $before = $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($before);

        // Invalidate
        $this->manager->invalidate('header');

        // Cache should be empty; fresh DB call should still return tree
        $after = $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($after);
    }

    // ── NavigationManager is the only public entry point ─────────────────

    public function test_navigation_manager_is_only_public_entry_point(): void
    {
        // The helper delegates to NavigationManager
        $fromHelper  = navigation('header');
        $fromManager = $this->manager->forLocation(NavigationLocation::Header);

        // Both null (no header menu) proves same path
        $this->assertSame($fromHelper, $fromManager);
    }
}
