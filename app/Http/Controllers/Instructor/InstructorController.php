<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Instructor\InstructorService;
use App\Services\Profile\UserEducationService;
use App\Services\Profile\UserExperienceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstructorController extends Controller
{
    public function __construct(
        private readonly InstructorService $instructorService,
        private readonly UserExperienceService $experienceService,
        private readonly UserEducationService $educationService,
    ) {}

    public function index(Request $request): View
    {
        $instructors = $this->instructorService->listing($request);

        return view('instructors.index', compact('instructors'));
    }

    public function show(Request $request, User $user): View
    {
        abort_unless($user->hasRole('instructor'), 404);

        $user->loadMissing(['profile.country', 'profile.state', 'profile.media', 'media']);

        $profile = $user->profile;
        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $user->id;
        $canManage = $isOwner || ($viewer && $viewer->can('Update:User'));

        abort_if($profile->profile_visibility === 'private' && ! $canManage, 403);
        abort_if($profile->profile_visibility === 'members_only' && ! $viewer, 403);

        $experiences = $this->experienceService->timeline($user);
        $educations = $this->educationService->timeline($user);
        $currentPosition = $this->experienceService->currentPosition($user);
        $stats = $this->instructorService->stats($user);
        $related = $this->instructorService->related($user);

        $skills = $experiences
            ->flatMap(fn ($e) => $e->skills ?? [])
            ->filter()
            ->unique()
            ->values();

        $certificates = $educations->filter(fn ($e) => filled($e->certificate_number));

        return view('instructors.show', compact(
            'user',
            'profile',
            'experiences',
            'educations',
            'currentPosition',
            'stats',
            'related',
            'skills',
            'certificates',
        ));
    }
}
