<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.list');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('posts.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('posts.delete');
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->can('posts.restore');
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->can('posts.delete');
    }
}

