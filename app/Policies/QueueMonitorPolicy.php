<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class QueueMonitorPolicy
{
    use HandlesAuthorization;

    public function viewPage(AuthUser $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        try {
            return method_exists($user, 'hasPermissionTo')
                && $user->hasPermissionTo('queue_monitor.view');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function isSuperAdmin(AuthUser $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }
}
