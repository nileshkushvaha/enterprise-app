<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

/**
 * Single-responsibility action: persists the new user + empty profile.
 * Does NOT dispatch jobs or assign roles — that is the Service's concern.
 */
final class RegisterUserAction
{
    public function execute(
        array $data,
        string $status = User::STATUS_PENDING,
        bool $mustChangePassword = false,
    ): User {
        return DB::transaction(function () use ($data, $status, $mustChangePassword): User {
            $fullName = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

            $user = User::create([
                'name' => $fullName ?: $data['first_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
                'status' => $status,
                'must_change_password' => $mustChangePassword,
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'phone' => $data['phone'] ?? null,
            ]);

            return $user;
        });
    }
}
