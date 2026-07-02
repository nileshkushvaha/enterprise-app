<?php

declare(strict_types=1);

namespace App\Services\Instructor;

use App\Models\User;
use App\Services\Profile\UserExperienceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-side helpers only — no CRUD here. Entry points for the public
 * instructor listing, detail page, and related-instructors widget.
 */
final class InstructorService
{
    public function __construct(
        private readonly UserExperienceService $experienceService,
    ) {}

    public function listing(Request $request): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('users.name', 'like', '%'.$q.'%')
                    ->orWhere('user_profiles.bio', 'like', '%'.$q.'%')
                    ->orWhere('user_profiles.headline', 'like', '%'.$q.'%')
                    ->orWhere('user_profiles.short_bio', 'like', '%'.$q.'%');
            });
        }

        $sort = $request->input('sort', 'featured');

        match ($sort) {
            'name' => $query->orderBy('users.name'),
            'newest' => $query->orderByDesc('users.created_at'),
            default => $query->orderByRaw('user_profiles.is_featured DESC')
                ->orderBy('user_profiles.featured_order')
                ->orderBy('users.name'),
        };

        return $query->paginate(12)->withQueryString();
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->baseQuery()
            ->where('user_profiles.is_featured', true)
            ->orderBy('user_profiles.featured_order')
            ->orderBy('users.name')
            ->limit($limit)
            ->get();
    }

    public function related(User $instructor, int $limit = 4): Collection
    {
        return $this->baseQuery()
            ->where('users.id', '!=', $instructor->id)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function stats(User $instructor): array
    {
        return [
            'years_experience' => $this->experienceService->yearsOfExperience($instructor),
            'experience_count' => $instructor->experiences()->active()->count(),
            'education_count' => $instructor->educations()->active()->count(),
            // Stubbed — Course model does not exist yet
            'courses_count' => 0,
            'students_count' => 0,
            'avg_rating' => null,
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        return User::query()
            ->join('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->whereNull('user_profiles.deleted_at')
            ->where('users.status', 'active')
            ->where('user_profiles.profile_visibility', 'public')
            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'instructor'))
            ->with(['profile.media', 'profile.country', 'profile.state', 'media'])
            ->select('users.*');
    }
}
