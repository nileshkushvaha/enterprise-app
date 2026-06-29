<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AccountProtectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountUnlockController extends Controller
{
    public function __construct(private readonly AccountProtectionService $accountProtection) {}

    /**
     * Show the unlock confirmation page.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = User::where('email', $request->query('email'))->first();

        if (! $this->isValidUnlockRequest($user, $request->query('token', ''))) {
            return view('auth.unlock-invalid');
        }

        return view('auth.unlock', ['email' => $request->query('email')]);
    }

    /**
     * Process the unlock request.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $this->isValidUnlockRequest($user, $request->input('token', ''))) {
            return redirect()->route('auth.login')
                ->withErrors(['email' => 'This unlock link is invalid or has expired.']);
        }

        $this->accountProtection->manualUnlock($user, actor: $user, method: 'self_service');

        return redirect()->route('auth.login')
            ->with('success', 'Your account has been unlocked. You can now sign in.');
    }

    // ── Private ───────────────────────────────────────────────────────

    private function isValidUnlockRequest(?User $user, string $token): bool
    {
        if (! $user || ! $user->isLocked()) {
            return false;
        }

        if (! $user->unlock_token || ! $user->unlock_token_expires_at) {
            return false;
        }

        if ($user->unlock_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($user->unlock_token, hash('sha256', $token));
    }
}
