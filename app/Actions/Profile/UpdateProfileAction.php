<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

final class UpdateProfileAction
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {

            // ── Update core user fields ───────────────────────────────
            // Email is frozen — intentionally never written here, even if a
            // caller passes one in $data.
            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            ]);

            // ── Load existing profile (so we can fall back to saved values) ──
            $profile = $user->profile ?? new UserProfile(['user_id' => $user->id]);

            $existingNotifPrefs = $profile->notification_preferences ?? [];

            // ── Merge: only overwrite a field when the request actually sent it.
            //    Each tab only posts its own fields — other fields keep their
            //    existing DB value instead of being blanked to null.
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    // General tab fields
                    'phone' => array_key_exists('phone', $data) ? ($data['phone'] ?? null) : $profile->phone,
                    'gender' => array_key_exists('gender', $data) ? ($data['gender'] ?? null) : $profile->gender,
                    'date_of_birth' => array_key_exists('date_of_birth', $data) ? ($data['date_of_birth'] ?? null) : $profile->date_of_birth,
                    'address' => array_key_exists('address', $data) ? ($data['address'] ?? null) : $profile->address,
                    'city' => array_key_exists('city', $data) ? ($data['city'] ?? null) : $profile->city,
                    'state' => array_key_exists('state', $data) ? ($data['state'] ?? null) : $profile->state,
                    'country_id' => array_key_exists('country_id', $data) ? ($data['country_id'] ?? null) : $profile->country_id,
                    'postal_code' => array_key_exists('postal_code', $data) ? ($data['postal_code'] ?? null) : $profile->postal_code,

                    // Preferences tab fields — fall back to saved value to prevent null constraint errors
                    'timezone' => $data['timezone'] ?? $profile->timezone ?? 'Asia/Kolkata',
                    'language' => $data['language'] ?? $profile->language ?? 'en',
                    'date_format' => $data['date_format'] ?? $profile->date_format ?? 'Y-m-d',
                    'time_format' => $data['time_format'] ?? $profile->time_format ?? 'H:i',
                    'theme' => $data['theme'] ?? $profile->theme ?? 'dark',

                    // Notifications tab — merge individual keys so toggling one
                    // field doesn't reset the others to their defaults
                    'notification_preferences' => [
                        'email_notifications' => array_key_exists('email_notifications', $data)
                            ? (bool) $data['email_notifications']
                            : (bool) ($existingNotifPrefs['email_notifications'] ?? true),
                        'system_notifications' => array_key_exists('system_notifications', $data)
                            ? (bool) $data['system_notifications']
                            : (bool) ($existingNotifPrefs['system_notifications'] ?? true),
                        'marketing_emails' => array_key_exists('marketing_emails', $data)
                            ? (bool) $data['marketing_emails']
                            : (bool) ($existingNotifPrefs['marketing_emails'] ?? false),
                    ],
                ]
            );

            return $user->fresh(['profile']);
        });
    }
}
