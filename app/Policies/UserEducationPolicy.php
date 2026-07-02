<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UserEducation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Mirrors UserExperiencePolicy exactly — ownership OR Update:User, no role
 * names. Gate::before() already grants super_admin everything.
 */
class UserEducationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function view(AuthUser $authUser, UserEducation $education): bool
    {
        return $authUser->id === $education->user_id || $authUser->can('Update:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function update(AuthUser $authUser, UserEducation $education): bool
    {
        return $authUser->id === $education->user_id || $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser, UserEducation $education): bool
    {
        return $authUser->id === $education->user_id || $authUser->can('Update:User');
    }

    public function restore(AuthUser $authUser, UserEducation $education): bool
    {
        return $authUser->id === $education->user_id || $authUser->can('Update:User');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }
}
