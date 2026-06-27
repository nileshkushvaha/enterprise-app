<?php

namespace App\Policies;

use App\Models\PostCategory;
use App\Models\User;

class PostCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.list');
    }

    public function view(User $user, PostCategory $postCategory): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, PostCategory $postCategory): bool
    {
        return $user->can('posts.update');
    }

    public function delete(User $user, PostCategory $postCategory): bool
    {
        return $user->can('posts.delete');
    }

    public function restore(User $user, PostCategory $postCategory): bool
    {
        return $user->can('posts.restore');
    }

    public function forceDelete(User $user, PostCategory $postCategory): bool
    {
        return $user->can('posts.delete');
    }
}

