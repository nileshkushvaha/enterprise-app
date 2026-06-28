<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\Services\NavigationCacheManager;
use App\Navigation\Services\NavigationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private NavigationManager $manager;
    private NavigationCacheManager $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager      = app(NavigationManager::class);
        $this->cacheManager = app(NavigationCacheManager::class);
    }

    private function publishedMenu(array $attrs = []): NavigationMenu
    {
        return NavigationMenu::factory()->create(array_merge([
            'status'   => NavigationStatus::Published->value,
            'location' => NavigationLocation::Header->value,
        ], $attrs));
    }

    private function cacheKey(NavigationMenu $menu): string
    {
        return $this->cacheManager->cacheKey($menu);
    }

    // ── NavigationItem events ──────────────────────────────────────────────

    public function test_cache_invalidated_when_item_is_created(): void
    {
        $menu = $this->publishedMenu();
        $key  = $this->cacheKey($menu);

        // Prime the cache
        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        // Creating a new item should invalidate
        NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);

        $this->assertNull($this->cacheManager->get($key));
    }

    public function test_cache_invalidated_when_item_is_updated(): void
    {
        $menu = $this->publishedMenu();
        $item = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);
        $key  = $this->cacheKey($menu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        $item->update(['label' => 'Updated Label']);

        $this->assertNull($this->cacheManager->get($key));
    }

    public function test_cache_invalidated_when_item_is_deleted(): void
    {
        $menu = $this->publishedMenu();
        $item = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);
        $key  = $this->cacheKey($menu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        $item->delete();

        $this->assertNull($this->cacheManager->get($key));
    }

    public function test_cache_invalidated_when_item_is_restored(): void
    {
        $menu = $this->publishedMenu();
        $item = NavigationItem::factory()->create(['navigation_id' => $menu->id, 'is_active' => true]);
        $item->delete();

        $key = $this->cacheKey($menu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        $item->restore();

        $this->assertNull($this->cacheManager->get($key));
    }

    // ── NavigationMenu events ─────────────────────────────────────────────

    public function test_cache_invalidated_when_menu_is_updated(): void
    {
        $menu = $this->publishedMenu();
        $key  = $this->cacheKey($menu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        $menu->update(['name' => 'New Header Name']);

        $this->assertNull($this->cacheManager->get($key));
    }

    public function test_cache_invalidated_when_menu_is_deleted(): void
    {
        $menu = $this->publishedMenu();
        $key  = $this->cacheKey($menu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->assertNotNull($this->cacheManager->get($key));

        $menu->delete();

        $this->assertNull($this->cacheManager->get($key));
    }

    // ── Cross-menu isolation ──────────────────────────────────────────────

    public function test_other_menu_cache_not_invalidated_when_unrelated_item_changes(): void
    {
        $headerMenu = $this->publishedMenu(['location' => NavigationLocation::Header->value, 'slug' => 'h']);
        $footerMenu = $this->publishedMenu(['location' => NavigationLocation::Footer->value, 'slug' => 'f']);

        $footerItem = NavigationItem::factory()->create(['navigation_id' => $footerMenu->id, 'is_active' => true]);

        $headerKey = $this->cacheKey($headerMenu);
        $footerKey = $this->cacheKey($footerMenu);

        $this->manager->forLocation(NavigationLocation::Header);
        $this->manager->forLocation(NavigationLocation::Footer);

        $this->assertNotNull($this->cacheManager->get($headerKey));
        $this->assertNotNull($this->cacheManager->get($footerKey));

        // Update footer item — only footer cache should be invalidated
        $footerItem->update(['label' => 'Changed']);

        $this->assertNotNull($this->cacheManager->get($headerKey));
        $this->assertNull($this->cacheManager->get($footerKey));
    }
}
