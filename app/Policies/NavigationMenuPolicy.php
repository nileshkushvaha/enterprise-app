<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NavigationMenu;
use App\Models\User;

class NavigationMenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('navigation_menus.list');
    }

    public function view(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.view');
    }

    public function create(User $user): bool
    {
        return $user->can('navigation_menus.create');
    }

    public function update(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.update');
    }

    public function delete(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.delete');
    }

    public function restore(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.delete');
    }

    public function forceDelete(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.delete');
    }

    public function publish(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.publish');
    }

    public function duplicate(User $user, NavigationMenu $menu): bool
    {
        return $user->can('navigation_menus.create');
    }
}
