<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\LoginService;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly LoginService $loginService,
    ) {}

    public function showForm(Request $request, string $token): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $error = $this->passwordResetService->resetPassword(
            email: $request->input('email'),
            token: $request->input('token'),
            password: $request->input('password'),
            ipAddress: $request->ip() ?? '127.0.0.1',
        );

        if ($error !== null) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $error]);
        }

        // Auto-login after reset
        $this->loginService->attempt(
            email: $request->input('email'),
            password: $request->input('password'),
            remember: false,
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? '',
        );

        return redirect()->route('dashboard')
            ->with('success', 'Password reset successfully. Welcome back!');
    }
}
