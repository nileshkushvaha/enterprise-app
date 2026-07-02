<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Support\Collection;

/**
 * Derived-data helpers only — CRUD is owned by Filament's
 * ExperiencesRelationManager (authorized via UserExperiencePolicy) plus
 * UserExperienceObserver for activity logging. This service exists for
 * read-side consumers: the public profile page and ProfileCompletionService.
 */
final class UserExperienceService
{
    /**
     * @return Collection<int, UserExperience>
     */
    public function timeline(User $user): Collection
    {
        return $user->experiences()->active()->get();
    }

    public function currentPosition(User $user): ?UserExperience
    {
        return $user->experiences()->active()->where('is_current', true)->first();
    }

    public function yearsOfExperience(User $user): float
    {
        $totalDays = $user->experiences()->active()->get()
            ->sum(fn (UserExperience $experience): float => (float) $experience->start_date->diffInDays($experience->end_date ?? now()));

        return round($totalDays / 365, 1);
    }
}
