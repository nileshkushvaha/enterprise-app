<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.list');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('posts.update');
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('posts.delete');
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->can('posts.restore');
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can('posts.delete');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can('posts.publish');
    }
}

