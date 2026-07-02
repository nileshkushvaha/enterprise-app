<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UserExperience;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ownership OR the existing Update:User admin permission — no role names.
 * Gate::before() already grants super_admin everything before this ever
 * runs. This is what authorizes Filament's ExperiencesRelationManager
 * create/edit/delete actions today, and is ready for a frontend
 * self-edit surface later without any extra wiring.
 */
class UserExperiencePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function view(AuthUser $authUser, UserExperience $experience): bool
    {
        return $authUser->id === $experience->user_id || $authUser->can('Update:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function update(AuthUser $authUser, UserExperience $experience): bool
    {
        return $authUser->id === $experience->user_id || $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser, UserExperience $experience): bool
    {
        return $authUser->id === $experience->user_id || $authUser->can('Update:User');
    }

    public function restore(AuthUser $authUser, UserExperience $experience): bool
    {
        return $authUser->id === $experience->user_id || $authUser->can('Update:User');
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
