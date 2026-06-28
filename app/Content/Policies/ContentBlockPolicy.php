<?php

declare(strict_types=1);

namespace App\Content\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Content\Models\ContentBlock;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentBlockPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContentBlock');
    }

    public function view(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('View:ContentBlock');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContentBlock');
    }

    public function update(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('Update:ContentBlock');
    }

    public function delete(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('Delete:ContentBlock');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContentBlock');
    }

    public function restore(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('Restore:ContentBlock');
    }

    public function forceDelete(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('ForceDelete:ContentBlock');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ContentBlock');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ContentBlock');
    }

    public function replicate(AuthUser $authUser, ContentBlock $contentBlock): bool
    {
        return $authUser->can('Replicate:ContentBlock');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ContentBlock');
    }

}