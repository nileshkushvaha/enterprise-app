<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CacheManagerPolicy
{
    use HandlesAuthorization;

    public function viewPage(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user)
            || $user->hasPermissionTo('cache_manager.view');
    }

    public function clearApplicationCache(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user)
            || $user->hasPermissionTo('cache_manager.clear');
    }

    public function clearViewCache(AuthUser $user): bool
    {
        return $this->clearApplicationCache($user);
    }

    public function clearRouteCache(AuthUser $user): bool
    {
        return $this->clearApplicationCache($user);
    }

    public function clearConfigCache(AuthUser $user): bool
    {
        return $this->clearApplicationCache($user);
    }

    public function clearEventCache(AuthUser $user): bool
    {
        return $this->clearApplicationCache($user);
    }

    public function optimize(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user)
            || $user->hasPermissionTo('cache_manager.optimize');
    }

    public function optimizeClear(AuthUser $user): bool
    {
        return $this->optimize($user);
    }

    private function isSuperAdmin(AuthUser $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }
}
