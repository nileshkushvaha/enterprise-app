<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Homepage ────────────────────────────────────────────────────────
Route::get('/', fn () => view('home'))->name('home');

// ── Frontend Auth ───────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function (): void {

    // Registration
    Route::get('/register',  [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Email Verification
    Route::get('/verify-email', fn () => view('auth.verify-email'))
        ->middleware('auth')
        ->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        $request->user()->update(['status' => \App\Models\User::STATUS_ACTIVE]);
        return redirect('/auth/login')->with('success', 'Email verified! You can now sign in.');
    })->middleware(['auth', 'signed'])->name('verification.verify');

    Route::post('/resend-verification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('resent', true);
    })->middleware(['auth', 'throttle:6,1'])->name('verification.resend');

    // Placeholder routes for future modules (Login, Forgot Password, Reset)
    Route::get('/login',    fn () => view('auth.login'))->name('login');
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
});

