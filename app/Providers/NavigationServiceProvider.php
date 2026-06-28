<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Navigation\Contracts\NavigationCacheInterface;
use App\Navigation\Contracts\NavigationPermissionInterface;
use App\Navigation\Contracts\NavigationRendererInterface;
use App\Navigation\Contracts\NavigationRepositoryInterface;
use App\Navigation\Drivers\AnchorLinkDriver;
use App\Navigation\Drivers\CategoryLinkDriver;
use App\Navigation\Drivers\EmailLinkDriver;
use App\Navigation\Drivers\ExternalLinkDriver;
use App\Navigation\Drivers\PageLinkDriver;
use App\Navigation\Drivers\PhoneLinkDriver;
use App\Navigation\Drivers\PostLinkDriver;
use App\Navigation\Drivers\RouteLinkDriver;
use App\Navigation\Drivers\TagLinkDriver;
use App\Navigation\Drivers\UrlLinkDriver;
use App\Navigation\Registry\LinkTypeRegistry;
use App\Navigation\Services\NavigationCacheManager;
use App\Navigation\Services\NavigationItemService;
use App\Navigation\Services\NavigationManager;
use App\Navigation\Services\NavigationRenderer;
use App\Navigation\Services\NavigationRepository;
use App\Navigation\Services\PermissionEvaluator;
use App\Navigation\Services\UrlResolver;
use App\Observers\NavigationItemObserver;
use App\Observers\NavigationMenuObserver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindInterfaces();
        $this->bindSingletons();
        $this->registerLinkTypeRegistry();
    }

    public function boot(): void
    {
        $this->registerObservers();
    }

    private function bindInterfaces(): void
    {
        $this->app->bind(NavigationRepositoryInterface::class, NavigationRepository::class);
        $this->app->bind(NavigationRendererInterface::class, NavigationRenderer::class);
        $this->app->bind(NavigationPermissionInterface::class, PermissionEvaluator::class);
        $this->app->bind(NavigationCacheInterface::class, NavigationCacheManager::class);
    }

    private function bindSingletons(): void
    {
        $this->app->singleton(NavigationManager::class);
        $this->app->singleton(NavigationItemService::class);
        $this->app->singleton(LinkTypeRegistry::class);
        $this->app->singleton(UrlResolver::class);

        $this->app->singleton(NavigationCacheManager::class, function (Application $app): NavigationCacheManager {
            return new NavigationCacheManager(
                cache: $app->make(CacheRepository::class),
            );
        });
    }

    private function registerLinkTypeRegistry(): void
    {
        $this->app->afterResolving(LinkTypeRegistry::class, function (LinkTypeRegistry $registry, Application $app): void {
            $registry->register($app->make(PageLinkDriver::class));
            $registry->register($app->make(PostLinkDriver::class));
            $registry->register($app->make(CategoryLinkDriver::class));
            $registry->register($app->make(TagLinkDriver::class));
            $registry->register($app->make(RouteLinkDriver::class));
            $registry->register($app->make(UrlLinkDriver::class));
            $registry->register($app->make(ExternalLinkDriver::class));
            $registry->register($app->make(EmailLinkDriver::class));
            $registry->register($app->make(PhoneLinkDriver::class));
            $registry->register($app->make(AnchorLinkDriver::class));
        });
    }

    private function registerObservers(): void
    {
        NavigationMenu::observe(NavigationMenuObserver::class);
        NavigationItem::observe(NavigationItemObserver::class);
    }
}
