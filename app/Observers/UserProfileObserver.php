<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

/**
 * Logs instructor-specific profile lifecycle events to the 'instructor'
 * activity log name. Only fires when the subject user has the instructor
 * role, so generic profile updates on non-instructor accounts are silenced.
 */
class UserProfileObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function updated(UserProfile $profile): void
    {
        $user = $profile->user;

        if (! $user || ! $user->hasRole('instructor')) {
            return;
        }

        if ($profile->isDirty('instructor_status') && $profile->instructor_status !== null) {
            $event = 'profile_'.$profile->instructor_status->value;

            $this->log('instructor', $event, 'Instructor profile '.str_replace('_', ' ', $profile->instructor_status->value), $user, [
                'instructor_status' => $profile->instructor_status->value,
                'user_id' => $user->id,
            ]);
        }

        if ($profile->isDirty('profile_visibility')) {
            $this->log('instructor', 'visibility_changed', 'Instructor profile visibility changed', $user, [
                'profile_visibility' => $profile->profile_visibility,
                'user_id' => $user->id,
            ]);
        }

        if ($profile->isDirty('is_featured')) {
            $this->log('instructor', 'featured_changed', $profile->is_featured ? 'Instructor marked as featured' : 'Instructor removed from featured', $user, [
                'is_featured' => $profile->is_featured,
                'user_id' => $user->id,
            ]);
        }
    }

    private function log(string $logName, string $event, string $description, Model $subject, array $properties = []): void
    {
        /** @var User|null $causer */
        $causer = auth()->user();

        if ($causer instanceof User) {
            $this->auditTrail->logUser($causer, $logName, $event, $description, $subject, $properties);
        } else {
            $this->auditTrail->logSystem($logName, $event, $description, $subject, $properties);
        }
    }
}
