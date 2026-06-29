<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
        public bool $requiresApproval,
        public bool $autoVerified,
    ) {}
}
