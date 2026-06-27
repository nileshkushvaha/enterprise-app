<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    public function showForm(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->registrationService->register(
            data: $request->validated(),
            ipAddress: $request->ip() ?? '',
            userAgent: $request->userAgent() ?? '',
        );

        // Log the user in so the signed verification link works when clicked
        Auth::login($user);

        return redirect()
            ->route('auth.verification.notice')
            ->with('success', 'Account created! Please check your email to verify your address before signing in.');
    }
}
