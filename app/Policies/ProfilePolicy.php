<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ProfilePolicy
{
    public function view(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user): bool
    {
        return $user->isActive();
    }

    public function changePassword(User $user): bool
    {
        return $user->isActive();
    }
}
