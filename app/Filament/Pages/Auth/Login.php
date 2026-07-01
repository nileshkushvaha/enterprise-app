<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\PortalResolver;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;

/**
 * Admin Portal login (/admin/login). Only super_admin and manager may use
 * the Admin Portal — see PortalResolver, the single source of truth for
 * that rule. User::canAccessPanel() already guarantees Filament never
 * creates a session for an ineligible role (Filament's own attemptWhen()
 * checks canAccessPanel() before authenticating), so there is no session to
 * "log out" by the time a frontend-role user reaches this page.
 *
 * This override exists purely for UX: instead of Filament's generic
 * "these credentials do not match our records" (misleading for a frontend
 * user who typed their real, correct password), show the friendly message
 * the Portal Architecture calls for and send them to their own portal's
 * login page instead of leaving them stuck on /admin/login.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        if ($this->credentialsBelongToFrontendOnlyUser()) {
            $this->rejectFrontendUser();

            return null;
        }

        return parent::authenticate();
    }

    private function credentialsBelongToFrontendOnlyUser(): bool
    {
        $data = $this->form->getState();

        /** @var User|null $user */
        $user = User::where('email', strtolower((string) ($data['email'] ?? '')))->first();

        if (! $user || ! Hash::check((string) ($data['password'] ?? ''), $user->password)) {
            return false;
        }

        return app(PortalResolver::class)->usesFrontendPortal($user);
    }

    private function rejectFrontendUser(): void
    {
        $request = request();

        if ($request->hasSession()) {
            // Defensive — canAccessPanel() already prevents Filament's
            // attemptWhen() from creating a session for an ineligible role,
            // but invalidate and rotate the CSRF token anyway so nothing from
            // this request can be replayed.
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('error', 'You are not authorized to access the Administration Portal.');
        }

        $this->redirect(route('auth.login'));
    }
}
