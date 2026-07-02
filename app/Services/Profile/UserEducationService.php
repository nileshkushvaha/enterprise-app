<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\User;
use App\Models\UserEducation;
use Illuminate\Support\Collection;

/**
 * Derived-data helpers only — mirrors UserExperienceService. CRUD is owned
 * by Filament's EducationsRelationManager plus UserEducationObserver.
 */
final class UserEducationService
{
    /**
     * @return Collection<int, UserEducation>
     */
    public function timeline(User $user): Collection
    {
        return $user->educations()->active()->get();
    }

    public function latestEducation(User $user): ?UserEducation
    {
        return $user->educations()->active()
            ->orderByDesc('is_current')
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->first();
    }
}
