<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SchedulerMonitorPolicy
{
    use HandlesAuthorization;

    public function viewPage(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'scheduler_monitor.view');
    }

    public function runTask(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user) || $this->hasPermission($user, 'scheduler_monitor.run');
    }

    private function isSuperAdmin(AuthUser $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    private function hasPermission(AuthUser $user, string $permission): bool
    {
        try {
            return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }
}
