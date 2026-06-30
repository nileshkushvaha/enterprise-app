<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use App\Services\DashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly DashboardResolver $resolver,
    ) {}

    // ── Setup: show QR code page ──────────────────────────────────────

    public function setup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.show')
                ->with('active_tab', 'security')
                ->with('info', '2FA is already enabled.');
        }

        // Generate (or reuse pending) secret
        $secret = $user->two_factor_secret
            ? decrypt($user->two_factor_secret)
            : $this->twoFactor->enableSetup($user);

        $qrUrl = $this->twoFactor->qrCodeUrl($user, $secret);
        $qrSvg = $this->twoFactor->qrCodeSvg($qrUrl);
        $codes = $user->twoFactorRecoveryCodes();

        return view('auth.two-factor.setup', compact('secret', 'qrSvg', 'codes'));
    }

    // ── Confirm the 2FA code after scanning ──────────────────────────

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        /** @var User $user */
        $user = $request->user();

        if (! $this->twoFactor->confirm($user, $request->input('code'))) {
            return back()->withErrors(['code' => 'The code is incorrect. Please try again.']);
        }

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with('success', 'Two-factor authentication has been enabled! 🔐');
    }

    // ── Disable 2FA ───────────────────────────────────────────────────

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        /** @var User $user */
        $user = $request->user();

        $this->twoFactor->disable($user);

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    // ── Regenerate recovery codes ─────────────────────────────────────

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        /** @var User $user */
        $user = $request->user();
        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        return redirect()->route('profile.show')
            ->with('active_tab', 'security')
            ->with('success', 'Recovery codes regenerated. Please save the new codes.')
            ->with('recovery_codes', $codes);
    }

    // ── 2FA challenge during login ────────────────────────────────────

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('auth.login');
        }

        return view('auth.two-factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        if (! $request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('auth.login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = $request->session()->get('auth.2fa.user_id');
        $user = User::findOrFail($userId);

        // Try TOTP code
        if ($code = $request->input('code')) {
            $valid = $this->twoFactor->verifyCode($user, $code);
        }
        // Try recovery code
        elseif ($recoveryCode = $request->input('recovery_code')) {
            $valid = $this->twoFactor->verifyRecoveryCode($user, $recoveryCode);
        } else {
            $valid = false;
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'The code provided is invalid or has already been used.']);
        }

        // Complete login
        auth()->loginUsingId($userId, $request->session()->get('auth.2fa.remember', false));
        $request->session()->forget(['auth.2fa.user_id', 'auth.2fa.remember']);

        // Record successful login
        $user->recordSuccessfulLogin($request->ip(), $request->userAgent() ?? '');

        return redirect()->intended($this->resolver->redirectAfterLogin(auth()->user()));
    }
}
