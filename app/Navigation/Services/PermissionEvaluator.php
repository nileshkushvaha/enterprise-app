<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Enums\Navigation\NavigationVisibility;
use App\Navigation\Contracts\NavigationPermissionInterface;
use App\Navigation\DTOs\NavigationNode;
use Illuminate\Contracts\Auth\Authenticatable;

final class PermissionEvaluator implements NavigationPermissionInterface
{
    public function isVisible(NavigationNode $node, ?Authenticatable $user): bool
    {
        if (! $node->publishWindow->isActive()) {
            return false;
        }

        return match ($node->visibility) {
            NavigationVisibility::All => true,
            NavigationVisibility::Guest => $user === null,
            NavigationVisibility::Auth => $user !== null,
            NavigationVisibility::Roles => $this->hasRequiredRole($node, $user),
            NavigationVisibility::Permissions => $this->hasRequiredPermission($node, $user),
        };
    }

    private function hasRequiredRole(NavigationNode $node, ?Authenticatable $user): bool
    {
        if ($user === null || $node->requiredRoleIds === []) {
            return false;
        }

        if (! method_exists($user, 'roles')) {
            return false;
        }

        // Load user roles once (Spatie caches them) and compare in PHP — no extra queries.
        $userRoleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_intersect($node->requiredRoleIds, $userRoleIds) !== [];
    }

    private function hasRequiredPermission(NavigationNode $node, ?Authenticatable $user): bool
    {
        if ($user === null || $node->requiredPermissionIds === []) {
            return false;
        }

        if (! method_exists($user, 'getAllPermissions')) {
            return false;
        }

        // getAllPermissions() includes permissions via roles; Spatie caches this.
        $userPermIds = $user->getAllPermissions()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_intersect($node->requiredPermissionIds, $userPermIds) !== [];
    }
}
