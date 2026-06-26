<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use Spatie\Permission\Models\Role;

final class RegistrationService
{
    public function __construct(
        private readonly RegisterUserAction $registerAction,
    ) {}

    public function register(array $data, string $ipAddress, string $userAgent): User
    {
        // 1. Create user + profile via Action
        $user = $this->registerAction->execute($data);

        // 2. Assign default frontend role (student / user)
        $defaultRole = Role::whereName(User::DEFAULT_ROLE)->first();
        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }

        // 3. Dispatch event — listener queues all notifications + activity log
        UserRegistered::dispatch($user, $ipAddress, $userAgent);

        return $user;
    }
}
