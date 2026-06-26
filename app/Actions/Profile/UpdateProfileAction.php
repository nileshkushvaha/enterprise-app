<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateProfileAction
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {

            // ── Update core user fields ──────────────────────────────
            $user->update([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? null,
                'name'       => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'email'      => strtolower($data['email']),
            ]);

            // ── Upsert profile row ────────────────────────────────────
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone'         => $data['phone'] ?? null,
                    'gender'        => $data['gender'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'address'       => $data['address'] ?? null,
                    'city'          => $data['city'] ?? null,
                    'state'         => $data['state'] ?? null,
                    'country_id'    => $data['country_id'] ?? null,
                    'postal_code'   => $data['postal_code'] ?? null,
                    'timezone'      => $data['timezone'] ?? null,
                    'language'      => $data['language'] ?? null,
                    'date_format'   => $data['date_format'] ?? null,
                    'time_format'   => $data['time_format'] ?? null,
                    'theme'         => $data['theme'] ?? 'dark',
                    'notification_preferences' => [
                        'email_notifications'  => (bool) ($data['email_notifications']  ?? true),
                        'system_notifications' => (bool) ($data['system_notifications'] ?? true),
                        'marketing_emails'     => (bool) ($data['marketing_emails']     ?? false),
                    ],
                ]
            );

            return $user->fresh(['profile']);
        });
    }
}
