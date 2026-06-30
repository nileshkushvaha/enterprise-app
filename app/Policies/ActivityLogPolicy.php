<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ActivityLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        try {
            return $user->hasPermissionTo('activity_log.view');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
