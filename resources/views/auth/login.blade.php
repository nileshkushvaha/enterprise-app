@extends('layouts.frontend')
@section('bare', true)

@section('title', 'Sign In — ' . config('app.name'))

@php
    $authSettings = app(\App\Settings\AuthenticationSettings::class);
    $regSettings  = app(\App\Settings\RegistrationSettings::class);
@endphp

@section('content')
<div class="auth-page" x-data="{
    showPass: false,
    loading: false,
    submit(e) {
        this.loading = true;
        e.target.closest('form').submit();
    }
}">

    {{-- ── LEFT DECORATIVE PANEL ───────────────────────────────────────── --}}
    <div class="auth-left-panel justify-between p-10 xl:p-14">

        {{-- Animated background orbs --}}
        <div class="bg-orb w-[28rem] h-[28rem] bg-indigo-600/20 top-[-8rem] left-[-8rem]"></div>
        <div class="bg-orb w-[22rem] h-[22rem] bg-violet-600/15 bottom-[-6rem] right-[-6rem]" style="animation-delay:3s"></div>
        <div class="bg-orb w-[16rem] h-[16rem] bg-blue-500/10 top-[40%] right-[10%]" style="animation-delay:6s"></div>

        {{-- Mesh pattern overlay --}}
        <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(99,102,241,.07) 1px,transparent 1px);background-size:36px 36px;"></div>

        <div class="relative z-10">
            {{-- Logo → links to home --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3 mb-16 group w-fit">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 group-hover:shadow-indigo-500/50 transition-shadow">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
            </a>

            {{-- Headline --}}
            <div class="mb-12">
                <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
                    Welcome<br>back! 👋
                </h1>
                <p class="text-slate-400 text-lg leading-relaxed">
                    Continue your learning journey and unlock your full potential.
                </p>
            </div>

            {{-- Feature bullets --}}
            <div class="space-y-5">
                @foreach([
                    ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'title' => '200+ Expert Tutors', 'desc' => 'Verified professionals in every field'],
                    ['icon' => 'M15 10l4.553-2.069A1 1 0 0121 8.868V15.13a1 1 0 01-1.447.899L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'title' => 'Live Interactive Sessions', 'desc' => 'Real-time HD video with screen sharing'],
                    ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'title' => 'Smart Progress Tracking', 'desc' => 'AI-powered insights and milestones'],
                ] as $feat)
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-500/15 border border-indigo-500/25 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $feat['icon'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">{{ $feat['title'] }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">{{ $feat['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Testimonial --}}
        <div class="relative z-10 mt-12">
            <div class="glass-dark rounded-2xl p-5">
                <div class="flex items-center gap-1 mb-3">
                    @for($i=0;$i<5;$i++)
                    <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    @endfor
                </div>
                <p class="text-slate-300 text-sm leading-relaxed italic">"{{ config('app.name') }} completely transformed how I learn. My exam scores improved by 40% in just 3 months!"</p>
                <div class="flex items-center gap-2 mt-3">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-xs font-bold text-white">P</div>
                    <div>
                        <p class="text-white text-xs font-semibold">Priya Sharma</p>
                        <p class="text-slate-500 text-xs">Medical Student</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT FORM PANEL ────────────────────────────────────────────── --}}
    <div class="auth-right-panel">

        {{-- Background subtle orbs for right panel --}}
        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-600/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-violet-600/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 w-full max-w-md">

            {{-- Mobile: logo + back to home --}}
            <div class="flex items-center justify-between mb-8 lg:hidden">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
                </a>
                <a href="{{ route('home') }}" class="text-xs text-slate-500 hover:text-slate-300 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Home
                </a>
            </div>

            {{-- Desktop: back to home (right panel only, logo already on left) --}}
            <div class="hidden lg:flex justify-end mb-6">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to home
                </a>
            </div>

            {{-- Heading --}}
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-white mb-2">Sign in</h2>
                @if($regSettings->self_registration_enabled)
                <p class="text-slate-400 text-sm">Don't have an account? <a href="{{ route('auth.register') }}" class="text-indigo-400 hover:text-indigo-300 font-medium transition">Create one free →</a></p>
                @endif
            </div>

            {{-- Session messages --}}
            @if(session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 p-4">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-emerald-300 text-sm">{{ session('success') }}</p>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-red-300 text-sm">{{ session('error') }}</p>
            </div>
            @endif

            {{-- Account locked notice --}}
            @if(session('locked'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-amber-500/10 border border-amber-500/25 p-4">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <p class="text-amber-300 text-sm">{{ session('locked') }}</p>
            </div>
            @endif

            {{-- Unverified email notice --}}
            @if(session('unverified'))
            <div class="mb-5 rounded-xl bg-blue-500/10 border border-blue-500/25 p-4">
                <div class="flex items-start gap-3 mb-3">
                    <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <div>
                        <p class="text-blue-300 text-sm font-semibold">Email not verified</p>
                        <p class="text-blue-300/80 text-xs mt-0.5">{{ session('unverified') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('auth.verification.resend.guest') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ session('unverified_email', old('email')) }}">
                    <button type="submit"
                        class="w-full text-center text-xs font-medium text-blue-400 hover:text-white bg-blue-500/15 hover:bg-blue-500/30 border border-blue-500/30 rounded-lg py-2 px-3 transition">
                        ✉ Resend verification email
                    </button>
                </form>
            </div>
            @endif

            @if(! $authSettings->login_enabled)
            {{-- Login disabled — maintenance message --}}
            <div class="flex items-start gap-3 rounded-xl bg-amber-500/10 border border-amber-500/25 p-5 mb-6">
                <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-amber-300 text-sm font-semibold">Login temporarily unavailable</p>
                    <p class="text-amber-300/80 text-xs mt-1">We're performing maintenance. Please check back shortly.</p>
                </div>
            </div>
            @else

            {{-- Login Form --}}
            <form method="POST" action="{{ route('auth.login') }}" @submit="submit($event)" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="auth-label">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="you@example.com"
                        autocomplete="email"
                        class="auth-input @error('email') error @enderror"
                        required
                    >
                    @error('email')
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="auth-label mb-0">Password</label>
                        <a href="{{ route('auth.password.request') }}" class="text-xs text-indigo-400 hover:text-indigo-300 transition font-medium">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input
                            :type="showPass ? 'text' : 'password'"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            class="auth-input pr-11 @error('password') error @enderror"
                            required
                        >
                        <button type="button" @click="showPass = !showPass"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition focus:outline-none">
                            <svg x-show="!showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password')
                    <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- Remember me --}}
                @if($authSettings->remember_me_enabled)
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="remember" name="remember"
                        class="w-4 h-4 rounded bg-white/5 border border-white/15 text-indigo-500 focus:ring-indigo-500/30 focus:ring-2 cursor-pointer"
                        {{ old('remember') ? 'checked' : '' }}>
                    <label for="remember" class="text-sm text-slate-400 cursor-pointer select-none hover:text-slate-300 transition">Keep me signed in for 30 days</label>
                </div>
                @endif

                {{-- Submit --}}
                <button type="submit" class="auth-btn-primary" :disabled="loading">
                    <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-show="loading">Signing in…</span>
                    <span x-show="!loading" class="flex items-center gap-2">
                        Sign in
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </span>
                </button>
            </form>

            @endif {{-- /login_enabled --}}

            @if($regSettings->self_registration_enabled)
            {{-- Divider --}}
            <div class="my-7 flex items-center gap-3">
                <div class="flex-1 h-px bg-white/[0.07]"></div>
                <span class="text-xs text-slate-600 font-medium">NEW TO {{ strtoupper(config('app.name')) }}?</span>
                <div class="flex-1 h-px bg-white/[0.07]"></div>
            </div>

            {{-- Register CTA --}}
            <a href="{{ route('auth.register') }}"
               class="block w-full text-center px-5 py-3 rounded-xl border border-white/[0.12] text-slate-300 hover:bg-white/[0.05] hover:text-white transition font-medium text-sm">
                Create your free account
            </a>
            @endif

            <p class="text-center text-xs text-slate-600 mt-6">
                By signing in, you agree to our
                <a href="#" class="text-slate-500 hover:text-slate-400 transition">Terms</a> and
                <a href="#" class="text-slate-500 hover:text-slate-400 transition">Privacy Policy</a>
            </p>
        </div>
    </div>
</div>
@endsection
