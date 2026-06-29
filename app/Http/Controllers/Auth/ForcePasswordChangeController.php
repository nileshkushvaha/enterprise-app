<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcePasswordChangeRequest;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Services\Auth\PasswordHistoryService;
use App\Services\Auth\PasswordLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function __construct(
        private readonly PasswordLifecycleService $lifecycle,
        private readonly PasswordHistoryService $historyService,
    ) {}

    public function showForm(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $this->lifecycle->mustChange($user)) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.force-password-change');
    }

    public function store(ForcePasswordChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldHash = $user->password;

        $this->historyService->assertNotReused($user, $request->validated('password'));

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        $this->historyService->store($user, $oldHash);

        // Regenerate session to bind the updated credentials
        $request->session()->regenerate();

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('password_changed')
            ->withProperties(['ip' => $request->ip(), 'reason' => 'forced_first_login'])
            ->log('Password changed on first login');

        $user->notify(new PasswordChangedNotification(
            ipAddress: $request->ip() ?? '127.0.0.1',
            changedAt: Carbon::now()->toDateTimeString(),
        ));

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Password updated successfully. Welcome!');
    }
}
