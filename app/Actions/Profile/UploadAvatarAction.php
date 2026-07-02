<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Handles both the 'avatar' and 'cover' Media Library collections on
 * UserProfile. Both collections are singleFile(), so adding new media
 * automatically replaces (and deletes the disk file for) whatever was
 * there before — no manual old-file cleanup needed.
 */
final class UploadAvatarAction
{
    public function execute(User $user, UploadedFile $file, string $collection): void
    {
        $user->profile
            ->addMedia($file)
            ->toMediaCollection($collection);
    }

    public function delete(User $user, string $collection): void
    {
        $user->profile->clearMediaCollection($collection);
    }
}
