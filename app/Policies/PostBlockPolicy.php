<?php

namespace App\Policies;

use App\Models\PostBlock;
use App\Models\User;

class PostBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.view');
    }

    public function view(User $user, PostBlock $postBlock): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, PostBlock $postBlock): bool
    {
        return $user->can('posts.update');
    }

    public function delete(User $user, PostBlock $postBlock): bool
    {
        return $user->can('posts.delete');
    }

    public function restore(User $user, PostBlock $postBlock): bool
    {
        return $user->can('posts.restore');
    }

    public function forceDelete(User $user, PostBlock $postBlock): bool
    {
        return $user->can('posts.delete');
    }
}

