<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\LoginResult;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Performs the raw credential check against the guard.
 * Does NOT handle pre-checks (locking, status) or failed-attempt tracking —
 * that is LoginService's responsibility via LoginSecurityService.
 */
final class AttemptLoginAction
{
    public function execute(string $email, string $password, bool $remember): LoginResult
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return LoginResult::InvalidCredentials;
        }

        // Credential is correct — log the user in
        Auth::login($user, $remember);

        return LoginResult::Success;
    }
}
