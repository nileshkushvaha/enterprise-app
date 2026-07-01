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
use Illuminate\Support\Facades\Cache;
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

        Cache::forget($this->completionCacheKey($user));

        return $updated;
    }

    /**
     * Weighted profile-completion percentage, cached per user since it is
     * read on every Account Portal page via AccountPortalComposer.
     */
    public function completion(User $user): int
    {
        return Cache::remember(
            $this->completionCacheKey($user),
            now()->addMinutes(10),
            function () use ($user): int {
                $user->loadMissing('profile');
                $profile = $user->profile;

                $checks = [
                    filled($user->first_name),
                    filled($user->last_name),
                    (bool) $user->email_verified_at,
                    filled($user->avatar),
                    filled($profile?->phone),
                    filled($profile?->address),
                    filled($profile?->country_id),
                ];

                $completed = count(array_filter($checks));

                return (int) round(($completed / count($checks)) * 100);
            },
        );
    }

    private function completionCacheKey(User $user): string
    {
        return "account.profile-completion.{$user->id}";
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

        Cache::forget($this->completionCacheKey($user));

        return $path;
    }

    public function deleteAvatar(User $user): void
    {
        $this->uploadAvatar->delete($user);
        Cache::forget($this->completionCacheKey($user));

        activity('profile')
            ->causedBy($user)
            ->performedOn($user)
            ->log('Profile photo removed');
    }
}
