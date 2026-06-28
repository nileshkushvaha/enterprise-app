<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadAvatarAction
{
    public function execute(User $user, UploadedFile $file): string
    {
        // Delete old avatar if it exists
        $this->deleteOldAvatar($user);

        // Generate a unique path: avatars/{user_id}/{uuid}.webp-or-original-ext
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid().'.'.$extension;
        $path = 'avatars/'.$user->id.'/'.$filename;

        // Store to public disk
        $file->storeAs('avatars/'.$user->id, $filename, 'public');

        // Update both user and profile rows
        $user->update(['avatar' => $path]);
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['avatar' => $path]
        );

        return $path;
    }

    public function delete(User $user): void
    {
        $this->deleteOldAvatar($user);

        $user->update(['avatar' => null]);
        $user->profile()?->update(['avatar' => null]);
    }

    private function deleteOldAvatar(User $user): void
    {
        $existing = $user->avatar ?? $user->profile?->avatar;

        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }
    }
}
