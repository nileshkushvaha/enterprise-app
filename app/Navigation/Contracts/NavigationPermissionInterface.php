<?php

declare(strict_types=1);

namespace App\Navigation\Contracts;

use App\Navigation\DTOs\NavigationNode;
use Illuminate\Contracts\Auth\Authenticatable;

interface NavigationPermissionInterface
{
    public function isVisible(NavigationNode $node, ?Authenticatable $user): bool;
}
