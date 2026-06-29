<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Models\Country;
use App\Services\Profile\ProfileService;
use App\Services\Profile\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly SessionService $sessionService,
    ) {}

    public function show(Request $request): View
    {
        $user = auth()->user()->load('profile.country');
        $countries = Country::active()->orderBy('name')->get(['id', 'name', 'iso2', 'flag']);
        $timezones = \DateTimeZone::listIdentifiers();
        $loginHistory = $user->loginHistories()->limit(10)->get();
        $activeSessions = $this->sessionService->getSessionsForUser($user);
        $currentSessionId = $request->session()->getId();

        return view('profile.show', compact(
            'user', 'countries', 'timezones', 'loginHistory', 'activeSessions', 'currentSessionId'
        ));
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->profileService->update(auth()->user(), $request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $this->profileService->changePassword(
            auth()->user(),
            $request->validated('password'),
            $request->ip() ?? '127.0.0.1',
        );

        // Rebind session password hash so user stays logged in
        $request->session()->put(
            'password_hash_web',
            auth()->user()->getAuthPassword(),
        );

        return back()
            ->with('success', 'Password changed successfully.')
            ->with('active_tab', 'security');
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $path = $this->profileService->uploadAvatar(
            auth()->user(),
            $request->file('avatar')
        );

        return response()->json([
            'success' => true,
            'url' => asset('storage/'.$path),
            'path' => $path,
        ]);
    }

    public function deleteAvatar(): JsonResponse
    {
        $this->profileService->deleteAvatar(auth()->user());

        return response()->json(['success' => true]);
    }
}
