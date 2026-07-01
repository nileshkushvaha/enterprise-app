<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\PasswordResetService;
use App\Services\PortalResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly PortalResolver $portal,
    ) {}

    public function showForm(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect($this->portal->loginRedirect(auth()->user()));
        }

        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // Always send reset link regardless of whether email exists
        // This prevents email enumeration attacks
        $this->passwordResetService->sendResetLink($request->input('email'));

        return back()->with('status', __('We have emailed your password reset link. Please check your inbox.'));
    }
}
