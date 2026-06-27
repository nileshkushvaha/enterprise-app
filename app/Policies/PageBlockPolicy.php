<?php

namespace App\Policies;

use App\Models\PageBlock;
use App\Models\User;

class PageBlockPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pages.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PageBlock $pageBlock): bool
    {
        return $user->can('pages.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('pages.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PageBlock $pageBlock): bool
    {
        return $user->can('pages.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PageBlock $pageBlock): bool
    {
        return $user->can('pages.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PageBlock $pageBlock): bool
    {
        return $user->can('pages.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PageBlock $pageBlock): bool
    {
        return $user->can('pages.delete');
    }
}
