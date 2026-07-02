<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\DTOs\AccountProfileSummaryData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\Profile\UserEducationService;
use App\Services\Profile\UserExperienceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public-view-only — no self-edit surface here. CRUD for Experience/
 * Education lives exclusively in Filament's Relation Managers. Lives
 * outside the auth-gated `profile.` route group so guests can load a
 * `public` visibility profile.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly UserExperienceService $experienceService,
        private readonly UserEducationService $educationService,
    ) {}

    public function show(Request $request, User $user): View
    {
        $user->loadMissing(['profile.country', 'profile.state', 'profile.media']);
        $profile = $user->profile;
        $viewer = $request->user();

        $isOwner = $viewer && $viewer->id === $user->id;
        $canManage = $isOwner || ($viewer && $viewer->can('Update:User'));

        abort_if($profile->profile_visibility === 'private' && ! $canManage, 403);
        abort_if($profile->profile_visibility === 'members_only' && ! $viewer, 403);

        return view('profile.public', [
            'summary' => AccountProfileSummaryData::fromUser($user, $this->profileService->completion($user)),
            'profile' => $profile,
            'experiences' => $this->experienceService->timeline($user),
            'educations' => $this->educationService->timeline($user),
            'currentPosition' => $this->experienceService->currentPosition($user),
            'yearsOfExperience' => $this->experienceService->yearsOfExperience($user),
            'latestEducation' => $this->educationService->latestEducation($user),
        ]);
    }
}
