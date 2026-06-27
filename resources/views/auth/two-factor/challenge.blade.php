@extends('layouts.frontend')
@section('bare', true)

@section('title', 'Two-Factor Authentication — ' . config('app.name'))

@section('content')
<div class="auth-page" x-data="{
    mode: 'code',
    loading: false,
    submit(e) {
        this.loading = true;
        e.target.closest('form').submit();
    }
}">

    {{-- Left panel --}}
    <div class="auth-left-panel justify-between p-10 xl:p-14">
        <div class="bg-orb w-[28rem] h-[28rem] bg-indigo-600/20 top-[-8rem] left-[-8rem]"></div>
        <div class="bg-orb w-[20rem] h-[20rem] bg-violet-600/15 bottom-[-6rem] right-[-4rem]" style="animation-delay:3s"></div>
        <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(99,102,241,.07) 1px,transparent 1px);background-size:36px 36px;"></div>

        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-16">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
            </div>
            <div class="mb-10">
                <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
                    One more<br>step 🔐
                </h1>
                <p class="text-slate-400 text-lg leading-relaxed">
                    Two-factor authentication keeps your account safe even if your password is compromised.
                </p>
            </div>
            <div class="space-y-5">
                @foreach([
                    ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'Zero-Knowledge Security', 'desc' => 'Codes generated on your device only'],
                    ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'title' => 'Phishing Resistant', 'desc' => 'TOTP codes expire every 30 seconds'],
                    ['icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'title' => 'Recovery Codes', 'desc' => 'Backup access if you lose your device'],
                ] as $feat)
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-500/15 border border-indigo-500/25 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $feat['icon'] }}"/></svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">{{ $feat['title'] }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">{{ $feat['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <p class="relative z-10 text-xs text-slate-600 mt-10">© {{ date('Y') }} {{ config('app.name') }}</p>
    </div>

    {{-- Right panel --}}
    <div class="auth-right-panel">
        <div class="w-full max-w-md">

            <div class="lg:hidden flex items-center justify-center gap-3 mb-10">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
            </div>

            <div class="auth-card p-8 shadow-2xl shadow-black/40">

                <div class="flex items-center justify-center mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/25 flex items-center justify-center float-y">
                        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>

                @if($errors->any())
                <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                    <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-red-300 text-sm">{{ $errors->first() }}</p>
                </div>
                @endif

                {{-- Tab toggle --}}
                <div class="flex rounded-xl bg-white/5 border border-white/8 p-1 mb-6">
                    <button type="button" @click="mode = 'code'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="mode === 'code' ? 'bg-indigo-600/80 text-white shadow-lg' : 'text-slate-400 hover:text-white'">
                        Authenticator Code
                    </button>
                    <button type="button" @click="mode = 'recovery'"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="mode === 'recovery' ? 'bg-indigo-600/80 text-white shadow-lg' : 'text-slate-400 hover:text-white'">
                        Recovery Code
                    </button>
                </div>

                <div x-show="mode === 'code'">
                    <h2 class="text-xl font-bold text-white mb-1 text-center">Enter 6-digit Code</h2>
                    <p class="text-sm text-slate-400 text-center mb-6">Open your authenticator app and enter the code</p>

                    <form method="POST" action="{{ route('auth.two-factor.verify') }}" @submit="submit($event)">
                        @csrf
                        <div class="mb-5">
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                autofocus autocomplete="one-time-code" placeholder="000 000"
                                class="auth-input text-center text-2xl font-mono tracking-[0.3em]"
                                value="{{ old('code') }}">
                        </div>
                        <button type="submit" class="auth-btn-primary" :disabled="loading">
                            <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-show="!loading">Verify →</span>
                            <span x-show="loading">Verifying…</span>
                        </button>
                    </form>
                </div>

                <div x-show="mode === 'recovery'" style="display:none">
                    <h2 class="text-xl font-bold text-white mb-1 text-center">Recovery Code</h2>
                    <p class="text-sm text-slate-400 text-center mb-6">Enter one of your 8-character backup codes</p>

                    <form method="POST" action="{{ route('auth.two-factor.verify') }}" @submit="submit($event)">
                        @csrf
                        <div class="mb-5">
                            <input type="text" name="recovery_code" autocomplete="off"
                                placeholder="xxxxxxxxxx-xxxxxxxxxx"
                                class="auth-input font-mono text-center tracking-widest"
                                value="{{ old('recovery_code') }}">
                            <p class="mt-2 text-xs text-slate-500 text-center">Format: xxxxxxxxxx-xxxxxxxxxx</p>
                        </div>
                        <button type="submit" class="auth-btn-primary" :disabled="loading">
                            <span x-show="!loading">Use Recovery Code →</span>
                            <span x-show="loading">Verifying…</span>
                        </button>
                    </form>
                </div>

                <p class="text-center text-xs text-slate-600 mt-6">
                    Not your account?
                    <a href="{{ route('auth.logout') }}"
                       onclick="event.preventDefault(); document.getElementById('2fa-logout-form').submit();"
                       class="text-slate-400 hover:text-white transition">Sign out</a>
                    <form id="2fa-logout-form" action="{{ route('auth.logout') }}" method="POST" class="hidden">@csrf</form>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
