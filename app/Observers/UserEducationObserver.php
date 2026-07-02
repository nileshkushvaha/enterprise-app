<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserEducation;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

/**
 * Fires regardless of entry point (Filament's Relation Manager today, a
 * future frontend self-edit form later) — matches the ActivityObserver/
 * PageObserver convention already used throughout this app.
 */
class UserEducationObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function created(UserEducation $education): void
    {
        $this->log('education', 'education_added', 'Education added', $education, [
            'institution_name' => $education->institution_name,
            'degree' => $education->degree,
            'user_id' => $education->user_id,
        ]);
    }

    public function updated(UserEducation $education): void
    {
        $this->log('education', 'education_updated', 'Education updated', $education, [
            'institution_name' => $education->institution_name,
            'degree' => $education->degree,
            'user_id' => $education->user_id,
            'changes' => array_keys($education->getChanges()),
        ]);
    }

    public function deleted(UserEducation $education): void
    {
        $this->log('education', 'education_deleted', 'Education deleted', $education, [
            'institution_name' => $education->institution_name,
            'degree' => $education->degree,
            'user_id' => $education->user_id,
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
