<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Named gates for instructor public-profile viewing.
 * Registered via Gate::define() — not as a model policy — because User is
 * already auto-bound to UserPolicy by Laravel convention.
 */
class InstructorPolicy
{
    use HandlesAuthorization;

    public function viewAny(): bool
    {
        return true;
    }

    public function view(?User $authUser, User $instructor): bool
    {
        $profile = $instructor->profile;

        if (! $profile) {
            return false;
        }

        if ($profile->profile_visibility === 'public') {
            return true;
        }

        if ($authUser === null) {
            return false;
        }

        // Owner or admin can always view regardless of visibility
        return $authUser->id === $instructor->id || $authUser->can('Update:User');
    }
}
