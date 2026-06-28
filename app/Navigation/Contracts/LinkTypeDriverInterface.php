<?php

declare(strict_types=1);

namespace App\Navigation\Contracts;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\DTOs\ResolvedLink;

interface LinkTypeDriverInterface
{
    public function resolve(NavigationItem $item): ResolvedLink;

    public function supports(NavigationLinkType $type): bool;
}
