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
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $fullName = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

            $user = User::create([
                'name' => $fullName ?: $data['first_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'], // cast hashes automatically
                'status' => User::STATUS_PENDING,
            ]);

            // Eager-create profile with phone if provided
            UserProfile::create([
                'user_id' => $user->id,
                'phone' => $data['phone'] ?? null,
            ]);

            return $user;
        });
    }
}
