<?php

declare(strict_types=1);

use App\Enums\Navigation\NavigationLocation;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\Services\NavigationManager;

if (! function_exists('navigation')) {
    /**
     * Retrieve a published navigation tree by location slug or enum.
     *
     * Usage:
     *   navigation('header')
     *   navigation(NavigationLocation::Footer)
     */
    function navigation(string|NavigationLocation $location, ?string $locale = null): ?NavigationTree
    {
        $enum = $location instanceof NavigationLocation
            ? $location
            : NavigationLocation::from($location);

        return app(NavigationManager::class)->forLocation($enum, $locale);
    }
}
