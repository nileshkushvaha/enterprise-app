<?php

declare(strict_types=1);

namespace App\Navigation\Contracts;

use App\Enums\Navigation\NavigationLocation;
use App\Models\NavigationMenu;

interface NavigationRepositoryInterface
{
    public function findByLocation(NavigationLocation $location, ?string $locale = null): ?NavigationMenu;

    public function findBySlug(string $slug): ?NavigationMenu;
}
