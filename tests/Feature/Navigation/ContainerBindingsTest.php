<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Navigation\Contracts\NavigationCacheInterface;
use App\Navigation\Contracts\NavigationPermissionInterface;
use App\Navigation\Contracts\NavigationRendererInterface;
use App\Navigation\Contracts\NavigationRepositoryInterface;
use App\Navigation\Registry\LinkTypeRegistry;
use App\Navigation\Services\ActiveDetector;
use App\Navigation\Services\NavigationCacheManager;
use App\Navigation\Services\NavigationManager;
use App\Navigation\Services\NavigationRenderer;
use App\Navigation\Services\NavigationRepository;
use App\Navigation\Services\PermissionEvaluator;
use App\Navigation\Services\UrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContainerBindingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_manager_resolves_from_container(): void
    {
        $this->assertInstanceOf(NavigationManager::class, app(NavigationManager::class));
    }

    public function test_repository_interface_resolves_to_implementation(): void
    {
        $this->assertInstanceOf(NavigationRepository::class, app(NavigationRepositoryInterface::class));
    }

    public function test_renderer_interface_resolves_to_implementation(): void
    {
        $this->assertInstanceOf(NavigationRenderer::class, app(NavigationRendererInterface::class));
    }

    public function test_permission_interface_resolves_to_evaluator(): void
    {
        $this->assertInstanceOf(PermissionEvaluator::class, app(NavigationPermissionInterface::class));
    }

    public function test_cache_interface_resolves_to_cache_manager(): void
    {
        $this->assertInstanceOf(NavigationCacheManager::class, app(NavigationCacheInterface::class));
    }

    public function test_url_resolver_resolves_from_container(): void
    {
        $this->assertInstanceOf(UrlResolver::class, app(UrlResolver::class));
    }

    public function test_active_detector_resolves_from_container(): void
    {
        $this->assertInstanceOf(ActiveDetector::class, app(ActiveDetector::class));
    }

    public function test_navigation_manager_is_singleton(): void
    {
        $first = app(NavigationManager::class);
        $second = app(NavigationManager::class);

        $this->assertSame($first, $second);
    }

    public function test_link_type_registry_is_singleton(): void
    {
        $first = app(LinkTypeRegistry::class);
        $second = app(LinkTypeRegistry::class);

        $this->assertSame($first, $second);
    }

    public function test_link_type_registry_has_all_ten_drivers_registered(): void
    {
        $registry = app(LinkTypeRegistry::class);
        $types = $registry->registeredTypes();

        foreach (['page', 'post', 'category', 'tag', 'route', 'url', 'external', 'email', 'phone', 'anchor'] as $type) {
            $this->assertContains($type, $types, "Driver for [{$type}] is not registered.");
        }
    }
}
