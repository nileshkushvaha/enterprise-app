@extends('layouts.frontend')

@section('title', 'Reset Password — ' . config('app.name'))

@section('content')
<div class="auth-page" x-data="{
    sent: {{ session('status') ? 'true' : 'false' }},
    loading: false,
    email: '{{ old('email') }}',
    submit(e) {
        this.loading = true;
        e.target.closest('form').submit();
    }
}">

    {{-- ── LEFT PANEL ──────────────────────────────────────────────────── --}}
    <div class="auth-left-panel justify-between p-10 xl:p-14">
        <div class="bg-orb w-[26rem] h-[26rem] bg-indigo-600/18 top-[-8rem] left-[-8rem]"></div>
        <div class="bg-orb w-[20rem] h-[20rem] bg-violet-600/12 bottom-[-6rem] right-[-4rem]" style="animation-delay:5s"></div>
        <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(99,102,241,.06) 1px,transparent 1px);background-size:36px 36px;"></div>

        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-16">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
            </div>

            <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
                Reset in<br>2 easy steps
            </h1>
            <p class="text-slate-400 text-lg leading-relaxed mb-12">
                Regain access to your account quickly and securely.
            </p>

            {{-- Steps --}}
            <div class="space-y-0">
                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">1</div>
                        <div class="w-0.5 h-14 bg-gradient-to-b from-indigo-500/60 to-transparent mt-1"></div>
                    </div>
                    <div class="pt-1.5 pb-8">
                        <p class="text-white font-semibold">Enter your email address</p>
                        <p class="text-slate-500 text-sm mt-1">We'll send a secure reset link to your inbox</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-9 h-9 rounded-full bg-white/10 border border-white/15 flex items-center justify-center text-slate-400 font-bold text-sm flex-shrink-0">2</div>
                    </div>
                    <div class="pt-1.5">
                        <p class="text-slate-300 font-semibold">Click the link in your email</p>
                        <p class="text-slate-500 text-sm mt-1">You'll be taken to create a new secure password</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 mt-8">
            <div class="glass-dark rounded-2xl p-5 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/15 border border-amber-500/25 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div>
                    <p class="text-white text-sm font-semibold">Link valid for 60 minutes</p>
                    <p class="text-slate-500 text-xs mt-0.5">For your security, reset links expire after 1 hour. Request a new one if needed.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT PANEL ─────────────────────────────────────────────────── --}}
    <div class="auth-right-panel">
        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-600/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-violet-600/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="flex items-center justify-center gap-3 mb-8 lg:hidden">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white">{{ config('app.name') }}</span>
            </div>

            {{-- ──── STATE 1: FORM ──────────────────────────────────────── --}}
            <div x-show="!sent" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-white mb-2">Forgot password?</h2>
                    <p class="text-slate-400 text-sm">Enter your email and we'll send you a reset link.</p>
                </div>

                @if(session('status'))
                <div class="mb-5 flex items-start gap-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 p-4">
                    <svg class="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-emerald-300 text-sm">{{ session('status') }}</p>
                </div>
                @endif

                <form method="POST" action="{{ route('auth.password.email') }}" @submit="submit($event)" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="auth-label">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            class="auth-input @error('email') error @enderror"
                            required>
                        @error('email')
                        <p class="mt-1.5 text-xs text-red-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    <button type="submit" class="auth-btn-primary" :disabled="loading">
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-show="loading">Sending reset link…</span>
                        <span x-show="!loading" class="flex items-center gap-2">
                            Send reset link
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <a href="{{ route('auth.login') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-300 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back to sign in
                    </a>
                </div>
            </div>

            {{-- ──── STATE 2: SUCCESS ───────────────────────────────────── --}}
            <div x-show="sent" x-transition:enter="transition ease-out duration-400" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="text-center">

                {{-- Animated envelope --}}
                <div class="relative inline-flex items-center justify-center mb-8">
                    <div class="w-24 h-24 rounded-2xl bg-indigo-500/10 border border-indigo-500/25 flex items-center justify-center">
                        <svg class="w-12 h-12 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="absolute -top-1 -right-1 w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-white mb-2">Check your inbox!</h2>
                <p class="text-slate-400 text-sm leading-relaxed mb-2">
                    We've sent a password reset link to
                </p>
                <p class="text-indigo-400 font-semibold text-sm mb-6" x-text="email || 'your email address'"></p>

                {{-- Tips --}}
                <div class="glass rounded-xl p-4 text-left mb-6 space-y-2.5">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Didn't get it?</p>
                    @foreach(["Check your spam or junk folder", "Make sure you entered the right email", "The link will expire in 60 minutes"] as $tip)
                    <div class="flex items-center gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 flex-shrink-0"></div>
                        <p class="text-slate-400 text-xs">{{ $tip }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Resend form --}}
                <form method="POST" action="{{ route('auth.password.email') }}" class="mb-4">
                    @csrf
                    <input type="hidden" name="email" x-bind:value="email">
                    <button type="submit" class="w-full px-5 py-3 rounded-xl border border-white/[0.12] text-slate-300 hover:bg-white/[0.05] hover:text-white transition font-medium text-sm">
                        Resend reset link
                    </button>
                </form>

                <a href="{{ route('auth.login') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-300 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to sign in
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
