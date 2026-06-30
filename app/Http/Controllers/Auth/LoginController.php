<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\LoginResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use App\Services\DashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly DashboardResolver $resolver,
    ) {}

    public function showForm(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $result = $this->loginService->attempt(
            email: $request->input('email'),
            password: $request->input('password'),
            remember: (bool) $request->input('remember', false),
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? '',
            sessionId: $request->session()->getId(),
        );

        if ($result->isSuccessful()) {
            $request->session()->regenerate();

            return redirect()->intended($this->resolver->redirectAfterLogin(auth()->user()));
        }

        // Redirect to 2FA challenge
        if ($result === LoginResult::RequiresTwoFactor) {
            return redirect()->route('auth.two-factor.challenge');
        }

        // For email unverified — stay on login page with a clear notice + resend link
        if ($result === LoginResult::EmailUnverified) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->with('unverified', $result->message())
                ->with('unverified_email', $request->input('email'));
        }

        $errorMessage = $result->message();

        // Append remaining-attempts hint when credentials were wrong
        if ($result === LoginResult::InvalidCredentials) {
            $remaining = session('login_remaining_attempts');

            if ($remaining !== null && $remaining > 0) {
                $word = $remaining === 1 ? 'attempt' : 'attempts';
                $errorMessage .= " {$remaining} {$word} remaining.";
            }
        }

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => $errorMessage])
            ->with('login_result', $result->value);
    }
}
