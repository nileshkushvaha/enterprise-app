@extends('layouts.frontend')
@section('bare', true)

@section('title', 'Enable Two-Factor Authentication — ' . config('app.name'))

@section('content')
<div class="min-h-screen bg-[#05080F] px-4 py-12" x-data="{
    step: 1,
    copied: false,
    copySecret() {
        navigator.clipboard.writeText('{{ $secret }}').then(() => {
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        });
    }
}">
    {{-- Background --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-[-12rem] left-[-12rem] w-[44rem] h-[44rem] rounded-full bg-indigo-600/10 blur-[130px]"></div>
        <div class="absolute bottom-[-12rem] right-[-12rem] w-[44rem] h-[44rem] rounded-full bg-violet-600/8 blur-[130px]"></div>
        <div class="absolute inset-0" style="background-image:radial-gradient(circle,rgba(99,102,241,.04) 1px,transparent 1px);background-size:40px 40px;"></div>
    </div>

    <div class="max-w-xl mx-auto relative z-10">

        {{-- Logo --}}
        <div class="flex items-center justify-center gap-3 mb-10">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
        </div>

        {{-- Progress steps --}}
        <div class="flex items-center justify-center gap-3 mb-8">
            @foreach(['Scan QR', 'Save Codes', 'Verify'] as $i => $label)
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                     :class="{{ $i + 1 }} <= step ? 'bg-indigo-600 text-white' : 'bg-white/10 text-slate-500'">
                    <span x-show="{{ $i + 1 }} < step">✓</span>
                    <span x-show="{{ $i + 1 }} >= step">{{ $i + 1 }}</span>
                </div>
                <span class="text-xs transition-colors" :class="{{ $i + 1 }} <= step ? 'text-white' : 'text-slate-600'">{{ $label }}</span>
                @if($i < 2)
                <div class="w-8 h-px mx-1" :class="{{ $i + 2 }} <= step ? 'bg-indigo-500' : 'bg-white/10'"></div>
                @endif
            </div>
            @endforeach
        </div>

        <div class="auth-card p-8 shadow-2xl shadow-black/40">

            {{-- STEP 1: Scan QR --}}
            <div x-show="step === 1">
                <h1 class="text-2xl font-bold text-white mb-2 text-center">Scan with Authenticator App</h1>
                <p class="text-sm text-slate-400 text-center mb-7">
                    Use <strong class="text-slate-300">Google Authenticator</strong>, <strong class="text-slate-300">Authy</strong>,
                    or any TOTP app to scan this QR code.
                </p>

                {{-- QR Code --}}
                <div class="flex items-center justify-center mb-6">
                    <div class="p-4 rounded-2xl bg-[#0f0c29] border border-indigo-500/20 shadow-xl shadow-indigo-500/10">
                        {!! $qrSvg !!}
                    </div>
                </div>

                {{-- Manual entry --}}
                <div class="mb-6">
                    <p class="text-xs text-slate-500 text-center mb-2">Can't scan? Enter this key manually:</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 text-indigo-300 text-sm font-mono text-center tracking-widest break-all">{{ $secret }}</code>
                        <button type="button" @click="copySecret()"
                            class="flex-shrink-0 px-3 py-2.5 rounded-xl border border-white/10 hover:border-indigo-500/40 hover:bg-indigo-500/10 transition text-xs text-slate-400 hover:text-indigo-300">
                            <span x-show="!copied">📋 Copy</span>
                            <span x-show="copied">✓ Copied</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-indigo-500/5 border border-indigo-500/15 mb-6">
                    <svg class="w-4 h-4 text-indigo-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-indigo-300/80 leading-relaxed">
                        After scanning, your app will generate a new 6-digit code every 30 seconds.
                        Time-based One-Time Passwords (TOTP) — RFC 6238 compliant.
                    </p>
                </div>

                <button type="button" @click="step = 2" class="auth-btn-primary">
                    I've scanned the QR code →
                </button>
            </div>

            {{-- STEP 2: Save Recovery Codes --}}
            <div x-show="step === 2" style="display:none">
                <h1 class="text-2xl font-bold text-white mb-2 text-center">Save Your Recovery Codes</h1>
                <p class="text-sm text-slate-400 text-center mb-6">
                    Store these codes safely. Each can be used <strong class="text-white">once</strong> to access your account if you lose your authenticator.
                </p>

                <div class="grid grid-cols-2 gap-2 p-4 rounded-xl bg-[#080e1a] border border-white/8 mb-5 font-mono">
                    @foreach($codes as $code)
                    <div class="px-3 py-2 rounded-lg bg-white/5 border border-white/6 text-sm text-indigo-300 text-center tracking-widest select-all">{{ $code }}</div>
                    @endforeach
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-amber-500/5 border border-amber-500/15 mb-6">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <p class="text-xs text-amber-400/80 leading-relaxed">
                        <strong class="text-amber-300">⚠ Important:</strong> These codes will not be shown again. Save them in a password manager or print them now.
                    </p>
                </div>

                <button type="button" @click="step = 3" class="auth-btn-primary">
                    I've saved the codes →
                </button>
            </div>

            {{-- STEP 3: Verify Code --}}
            <div x-show="step === 3" style="display:none">
                <h1 class="text-2xl font-bold text-white mb-2 text-center">Verify Your Setup</h1>
                <p class="text-sm text-slate-400 text-center mb-6">
                    Enter the 6-digit code from your authenticator app to confirm 2FA is working correctly.
                </p>

                @if($errors->any())
                <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                    <p class="text-red-300 text-sm">{{ $errors->first() }}</p>
                </div>
                @endif

                <form method="POST" action="{{ route('auth.two-factor.confirm') }}">
                    @csrf
                    <div class="mb-5">
                        <label class="auth-label">Verification Code</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                            autofocus autocomplete="one-time-code" placeholder="000 000"
                            class="auth-input text-center text-2xl font-mono tracking-[0.3em]"
                            value="{{ old('code') }}">
                    </div>

                    <button type="submit" class="auth-btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Enable Two-Factor Auth
                    </button>
                </form>
            </div>

        </div>

        <p class="text-center text-xs text-slate-600 mt-5">
            Changed your mind?
            <a href="{{ route('profile.show') }}" class="text-slate-500 hover:text-slate-400 transition">Back to profile</a>
        </p>
    </div>
</div>
@endsection
