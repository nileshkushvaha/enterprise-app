<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserExperience;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

/**
 * Fires regardless of entry point (Filament's Relation Manager today, a
 * future frontend self-edit form later) — matches the ActivityObserver/
 * PageObserver convention already used throughout this app.
 */
class UserExperienceObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function created(UserExperience $experience): void
    {
        $this->log('experience', 'experience_added', 'Experience added', $experience, [
            'organization_name' => $experience->organization_name,
            'designation' => $experience->designation,
            'user_id' => $experience->user_id,
        ]);
    }

    public function updated(UserExperience $experience): void
    {
        $this->log('experience', 'experience_updated', 'Experience updated', $experience, [
            'organization_name' => $experience->organization_name,
            'designation' => $experience->designation,
            'user_id' => $experience->user_id,
            'changes' => array_keys($experience->getChanges()),
        ]);
    }

    public function deleted(UserExperience $experience): void
    {
        $this->log('experience', 'experience_deleted', 'Experience deleted', $experience, [
            'organization_name' => $experience->organization_name,
            'designation' => $experience->designation,
            'user_id' => $experience->user_id,
        ]);
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
