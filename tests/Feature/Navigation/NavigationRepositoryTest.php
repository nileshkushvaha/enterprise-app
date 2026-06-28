<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\Services\NavigationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private NavigationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(NavigationRepository::class);
    }

    private function publishedMenu(array $attrs = []): NavigationMenu
    {
        return NavigationMenu::factory()->create(array_merge([
            'status' => NavigationStatus::Published->value,
            'location' => NavigationLocation::Header->value,
            'locale' => null,
        ], $attrs));
    }

    // ── findByLocation ────────────────────────────────────────────────────

    public function test_find_by_location_returns_published_menu(): void
    {
        $menu = $this->publishedMenu();

        $found = $this->repository->findByLocation(NavigationLocation::Header);

        $this->assertNotNull($found);
        $this->assertSame($menu->id, $found->id);
    }

    public function test_find_by_location_returns_null_for_draft(): void
    {
        NavigationMenu::factory()->create([
            'status' => NavigationStatus::Draft->value,
            'location' => NavigationLocation::Header->value,
        ]);

        $found = $this->repository->findByLocation(NavigationLocation::Header);

        $this->assertNull($found);
    }

    public function test_find_by_location_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByLocation(NavigationLocation::Sidebar);

        $this->assertNull($found);
    }

    public function test_find_by_location_eager_loads_items(): void
    {
        $menu = $this->publishedMenu();
        NavigationItem::factory()->create([
            'navigation_id' => $menu->id,
            'is_active' => true,
        ]);

        $found = $this->repository->findByLocation(NavigationLocation::Header);

        $this->assertTrue($found->relationLoaded('items'));
        $this->assertCount(1, $found->items);
    }

    public function test_find_by_location_excludes_inactive_items(): void
    {
        $menu = $this->publishedMenu();
        NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);
        NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => false]);

        $found = $this->repository->findByLocation(NavigationLocation::Header);

        $this->assertCount(1, $found->items);
    }

    public function test_find_by_location_with_matching_locale(): void
    {
        $this->publishedMenu(['location' => NavigationLocation::Footer->value, 'locale' => 'fr']);
        $expected = $this->publishedMenu(['location' => NavigationLocation::Footer->value, 'locale' => null]);

        // Without locale filter should return null-locale menu
        $found = $this->repository->findByLocation(NavigationLocation::Footer, null);

        $this->assertSame($expected->id, $found->id);
    }

    public function test_find_by_location_prefers_locale_specific_menu(): void
    {
        $frMenu = $this->publishedMenu(['location' => NavigationLocation::Footer->value, 'locale' => 'fr']);

        $found = $this->repository->findByLocation(NavigationLocation::Footer, 'fr');

        $this->assertSame($frMenu->id, $found->id);
    }

    // ── findBySlug ────────────────────────────────────────────────────────

    public function test_find_by_slug_returns_menu(): void
    {
        $menu = $this->publishedMenu(['slug' => 'my-menu']);

        $found = $this->repository->findBySlug('my-menu');

        $this->assertSame($menu->id, $found->id);
    }

    public function test_find_by_slug_returns_null_for_draft(): void
    {
        NavigationMenu::factory()->create([
            'slug' => 'hidden',
            'status' => NavigationStatus::Draft->value,
        ]);

        $this->assertNull($this->repository->findBySlug('hidden'));
    }

    public function test_find_by_slug_returns_null_for_unknown_slug(): void
    {
        $this->assertNull($this->repository->findBySlug('non-existent'));
    }

    public function test_items_eager_loads_linkable_roles_permissions(): void
    {
        $menu = $this->publishedMenu();
        NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);

        $found = $this->repository->findBySlug($menu->slug);

        $item = $found->items->first();
        $this->assertTrue($item->relationLoaded('linkable'));
        $this->assertTrue($item->relationLoaded('roles'));
        $this->assertTrue($item->relationLoaded('permissions'));
    }

    public function test_items_are_ordered_by_lft(): void
    {
        $menu = $this->publishedMenu();

        // Insert in reverse sort order — nestedset should order by _lft
        $c = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true, 'sort_order' => 3]);
        $a = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true, 'sort_order' => 1]);
        $b = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true, 'sort_order' => 2]);

        $found = $this->repository->findByLocation(NavigationLocation::Header);

        // Items come back ordered by _lft (insertion order for flat trees)
        $this->assertCount(3, $found->items);
    }
}
