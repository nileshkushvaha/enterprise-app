<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AccountUnlockController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\SecurityController;
use App\Http\Controllers\Profile\SessionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Homepage ────────────────────────────────────────────────────────
Route::get('/', [App\Http\Controllers\PageController::class, 'home'])->name('home');

// ── Frontend Search + SEO ───────────────────────────────────────────
Route::get('/search', [App\Http\Controllers\SearchController::class, 'index'])->name('search.index');
Route::get('/sitemap.xml', [App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');

// ── Blog ─────────────────────────────────────────────────────────────
Route::get('/blog', [App\Http\Controllers\PostController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [App\Http\Controllers\PostController::class, 'show'])->name('blog.show');

// ── Contact Form Submission ─────────────────────────────────────────
Route::post('/contact/submit', [App\Http\Controllers\ContactFormController::class, 'submit'])
    ->middleware('throttle:10,1')
    ->name('contact.submit');

// ── Login alias — required by Laravel internals (Authenticate middleware, password broker) ──
Route::get('/login', [LoginController::class, 'showForm'])->name('login');

// ── Dashboard (authenticated users) ─────────────────────────────────
Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard')
    ->middleware(['auth', 'verified', App\Http\Middleware\EnsureAccountIsActive::class, 'session.track']);

// ── Frontend Auth (guests only) ─────────────────────────────────────
Route::name('auth.')->middleware('guest')->group(function (): void {

    // Registration
    Route::get('/register',  [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Login
    Route::get('/login',  [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Forgot Password
    Route::get('/forgot-password',  [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email')
        ->middleware('throttle:5,1');

    // Reset Password
    Route::get('/reset-password/{token}',  [ResetPasswordController::class, 'showForm'])->name('password.reset');
    Route::post('/reset-password',         [ResetPasswordController::class, 'store'])->name('password.update');

    // Account Unlock (self-service — accessible without login)
    Route::get('/unlock-account',  [AccountUnlockController::class, 'show'])->name('account.unlock');
    Route::post('/unlock-account', [AccountUnlockController::class, 'unlock'])->name('account.unlock.process');

    // Public resend verification (for users who try to login before verifying)
    Route::post('/resend-verification-email', function (Request $request) {
        $request->validate(['email' => 'required|email']);

        $user = \App\Models\User::where('email', strtolower($request->input('email')))
            ->whereNull('email_verified_at')
            ->first();

        // Always show success to prevent email enumeration
        if ($user) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Verification email sent! Please check your inbox.');
    })->middleware('throttle:3,1')->name('verification.resend.guest');
});

// ── Auth — requires authenticated user ──────────────────────────────
Route::name('auth.')->middleware('auth')->group(function (): void {

    // Logout
    Route::post('/logout', LogoutController::class)->name('logout');

    // Email Verification notice — redirect away if already verified
    Route::get('/verify-email', function () {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard')->with('success', 'Your email is already verified.');
        }

        $request->fulfill();
        $request->user()->update(['status' => \App\Models\User::STATUS_ACTIVE]);

        return redirect()->route('auth.verified');
    })->middleware('signed')->name('verification.verify');

    Route::post('/resend-verification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();
        return back()->with('resent', true);
    })->middleware('throttle:6,1')->name('verification.resend');

    // Verification success page
    Route::get('/email-verified', fn () => view('auth.verified'))->name('verified');

    // ── 2FA Challenge (session key set by LoginService) ──────────────
    Route::get('/two-factor/challenge',  [TwoFactorController::class, 'challenge'])->name('two-factor.challenge')
        ->withoutMiddleware('auth');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verify'])->name('two-factor.verify')
        ->withoutMiddleware('auth');
});

// ── 2FA Management (auth + verified + active) ────────────────────────
Route::prefix('two-factor')->name('auth.two-factor.')->middleware([
    'auth',
    'verified',
    App\Http\Middleware\EnsureAccountIsActive::class,
])->group(function (): void {
    Route::get('/setup',              [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/confirm',           [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::delete('/disable',         [TwoFactorController::class, 'disable'])->name('disable');
    Route::post('/recovery-codes',    [TwoFactorController::class, 'regenerateCodes'])->name('regenerate-codes');
});

// ── Profile (auth + verified + active) ──────────────────────────────
Route::prefix('profile')->name('profile.')->middleware([
    'auth',
    'verified',
    App\Http\Middleware\EnsureAccountIsActive::class,
    'session.track',
])->group(function (): void {

    Route::get('/',               [ProfileController::class, 'show'])->name('show');
    Route::post('/',              [ProfileController::class, 'update'])->name('update');
    Route::post('/password',      [ProfileController::class, 'changePassword'])->name('password');
    Route::post('/avatar',        [ProfileController::class, 'uploadAvatar'])->name('avatar.upload');
    Route::delete('/avatar',      [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');

    // Session Management
    Route::delete('/sessions/all',    [SessionController::class, 'revokeAll'])->name('sessions.revoke-all');
    Route::delete('/sessions/{id}',   [SessionController::class, 'revoke'])->name('sessions.revoke');

    // Security alert preferences
    Route::post('/security/alerts',   [SecurityController::class, 'updateAlerts'])->name('security.alerts');
});

// ── Admin Routes (auth + verified + active) ────────────────────────────
Route::prefix('admin')->name('admin.')->middleware([
    'auth',
    'verified',
    App\Http\Middleware\EnsureAccountIsActive::class,
    'session.track',
])->group(function (): void {
    // Page Preview
    Route::get('/pages/{page}/preview', App\Http\Controllers\Admin\PagePreviewController::class)->name('pages.preview');
    // Post Preview
    Route::get('/posts/{post}/preview', App\Http\Controllers\Admin\PostPreviewController::class)->name('posts.preview');
});

// ── Public Pages (CMS) Catch-all (must stay last) ───────────────────
Route::get('/{slug}', [App\Http\Controllers\PageController::class, 'show'])->name('page.show');
