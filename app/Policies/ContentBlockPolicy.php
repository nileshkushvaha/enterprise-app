<?php

namespace App\Policies;

use App\Content\Models\ContentBlock;
use App\Models\Page;
use App\Models\User;

/**
 * Unified policy replacing PageBlockPolicy + PostBlockPolicy.
 *
 * Delegates permission checks to the underlying content type:
 *  - Page blocks  → pages.* permissions
 *  - Post blocks  → posts.* permissions
 */
class ContentBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.view') || $user->can('posts.view');
    }

    public function view(User $user, ContentBlock $block): bool
    {
        return $this->permissionFor($user, $block, 'view');
    }

    public function create(User $user): bool
    {
        return $user->can('pages.create') || $user->can('posts.create');
    }

    public function update(User $user, ContentBlock $block): bool
    {
        return $this->permissionFor($user, $block, 'update');
    }

    public function delete(User $user, ContentBlock $block): bool
    {
        return $this->permissionFor($user, $block, 'delete');
    }

    public function restore(User $user, ContentBlock $block): bool
    {
        return $this->permissionFor($user, $block, 'restore');
    }

    public function forceDelete(User $user, ContentBlock $block): bool
    {
        return $this->permissionFor($user, $block, 'delete');
    }

    private function permissionFor(User $user, ContentBlock $block, string $ability): bool
    {
        $prefix = $block->blockable_type === (new Page)->getMorphClass() ? 'pages' : 'posts';

        return $user->can("{$prefix}.{$ability}");
    }
}
