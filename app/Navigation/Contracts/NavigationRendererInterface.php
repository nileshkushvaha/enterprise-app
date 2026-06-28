<?php

declare(strict_types=1);

namespace App\Navigation\Contracts;

use App\Models\NavigationMenu;
use App\Navigation\DTOs\NavigationTree;

interface NavigationRendererInterface
{
    public function render(NavigationMenu $menu): NavigationTree;
}
