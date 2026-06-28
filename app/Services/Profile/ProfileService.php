<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Profile\UploadAvatarAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

final class ProfileService
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfile,
        private readonly UploadAvatarAction $uploadAvatar,
    ) {}

    public function update(User $user, array $data): User
    {
        $updated = $this->updateProfile->execute($user, $data);

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['fields' => array_keys(array_filter($data))])
            ->log('Profile updated');

        return $updated;
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->log('Password changed');
    }

    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        $path = $this->uploadAvatar->execute($user, $file);

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['path' => $path])
            ->log('Profile photo updated');

        return $path;
    }

    public function deleteAvatar(User $user): void
    {
        $this->uploadAvatar->delete($user);

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->log('Profile photo removed');
    }
}
