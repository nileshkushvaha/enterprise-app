<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Profile\UploadAvatarAction;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Services\Auth\PasswordHistoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

final class ProfileService
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfile,
        private readonly UploadAvatarAction $uploadAvatar,
        private readonly PasswordHistoryService $historyService,
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

    public function changePassword(User $user, string $newPassword, string $ipAddress = '127.0.0.1'): void
    {
        $oldHash = $user->password;

        $this->historyService->assertNotReused($user, $newPassword);

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        $this->historyService->store($user, $oldHash);

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->event('password_changed')
            ->withProperties(['ip' => $ipAddress])
            ->log('Password changed');

        $user->notify(new PasswordChangedNotification(
            ipAddress: $ipAddress,
            changedAt: Carbon::now()->toDateTimeString(),
        ));
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
