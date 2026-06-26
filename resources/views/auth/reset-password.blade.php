@extends('layouts.frontend')

@section('title', 'Set New Password — ' . config('app.name'))

@section('content')
<div class="min-h-screen bg-[#05080F] flex items-center justify-center px-4 py-16 relative overflow-hidden"
     x-data="{
    showPass: false,
    showConfirm: false,
    password: '',
    confirmVal: '',
    strength: 0,
    strengthLabel: '',
    passwordsMatch: false,
    loading: false,

    checkStrength(val) {
        this.password = val;
        let s = 0;
        if (val.length >= 8)  s++;
        if (val.length >= 8) s++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) s++;
        if (/\d/.test(val)) s++;
        if (/[^A-Za-z0-9]/.test(val)) s++;
        this.strength = s;
        this.strengthLabel = ['','Very weak','Weak','Fair','Strong','Very strong'][s] || '';
        if (this.confirmVal) this.checkMatch(this.confirmVal);
    },

    checkMatch(val) {
        this.confirmVal = val;
        this.passwordsMatch = val === this.password;
    },

    submit(e) {
        if (this.confirmVal && !this.passwordsMatch) { e.preventDefault(); return; }
        this.loading = true;
    }
}">

    {{-- Background orbs --}}
    <div class="absolute top-[-12rem] left-[-12rem] w-[40rem] h-[40rem] rounded-full bg-indigo-600/15 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-12rem] right-[-12rem] w-[40rem] h-[40rem] rounded-full bg-violet-600/12 blur-[120px] pointer-events-none"></div>
    <div class="absolute top-[40%] right-[20%] w-[20rem] h-[20rem] rounded-full bg-blue-600/8 blur-[100px] pointer-events-none"></div>
    <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(99,102,241,.04) 1px,transparent 1px);background-size:40px 40px;"></div>

    <div class="relative z-10 w-full max-w-md">

        {{-- Logo --}}
        <div class="flex items-center justify-center gap-3 mb-10">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
        </div>

        {{-- Card --}}
        <div class="auth-card p-8 shadow-2xl shadow-black/40">

            {{-- Icon + heading --}}
            <div class="text-center mb-8">
                <div class="inline-flex w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-white mb-1">Set new password</h2>
                <p class="text-slate-400 text-sm">Create a strong password to secure your account</p>
            </div>

            @if($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="space-y-1">
                    @foreach($errors->all() as $error)
                    <p class="text-red-300 text-sm">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('auth.password.update') }}" @submit.prevent="submit($event); if(!loading) return; $el.submit()" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ old('email', $email ?? '') }}">

                {{-- New password --}}
                <div>
                    <label for="password" class="auth-label">New password</label>
                    <div class="relative">
                        <input :type="showPass ? 'text' : 'password'"
                            id="password" name="password"
                            placeholder="Min. 8 characters"
                            autocomplete="new-password"
                            @input="checkStrength($event.target.value)"
                            class="auth-input pr-11 @error('password') error @enderror" required>
                        <button type="button" @click="showPass = !showPass"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition focus:outline-none">
                            <svg x-show="!showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>

                    <div x-show="password.length > 0" class="mt-2.5">
                        <div class="flex gap-1.5 mb-1.5">
                            <template x-for="i in 5">
                                <div class="flex-1 h-1.5 rounded-full transition-all duration-300"
                                    :class="i <= strength
                                        ? (strength <= 1 ? 'bg-red-500' : strength <= 2 ? 'bg-amber-500' : strength <= 3 ? 'bg-yellow-400' : 'bg-emerald-400')
                                        : 'bg-white/10'"></div>
                            </template>
                        </div>
                        <p class="text-xs transition-colors"
                            :class="strength <= 1 ? 'text-red-400' : strength <= 2 ? 'text-amber-400' : strength <= 3 ? 'text-yellow-400' : 'text-emerald-400'"
                            x-text="strengthLabel"></p>
                    </div>

                    @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Confirm password --}}
                <div>
                    <label for="password_confirmation" class="auth-label">Confirm new password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'"
                            id="password_confirmation" name="password_confirmation"
                            placeholder="Repeat your new password"
                            autocomplete="new-password"
                            @input="checkMatch($event.target.value)"
                            class="auth-input pr-16">
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                            <div x-show="confirmVal.length > 0">
                                <svg x-show="passwordsMatch" class="w-4.5 h-4.5 text-emerald-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <svg x-show="!passwordsMatch" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <button type="button" @click="showConfirm = !showConfirm"
                                class="text-slate-500 hover:text-slate-300 transition focus:outline-none">
                                <svg x-show="!showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <p x-show="confirmVal.length > 0 && !passwordsMatch" class="mt-1 text-xs text-red-400">Passwords don't match</p>
                </div>

                {{-- Submit --}}
                <button type="submit" class="auth-btn-primary mt-2" :disabled="loading || (confirmVal.length > 0 && !passwordsMatch)">
                    <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-show="loading">Setting new password…</span>
                    <span x-show="!loading" class="flex items-center gap-2">
                        Set new password & sign in
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </span>
                </button>
            </form>

            <div class="mt-5 text-center">
                <a href="{{ route('auth.login') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-300 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to sign in
                </a>
            </div>
        </div>

        {{-- Security note --}}
        <p class="text-center text-xs text-slate-600 mt-5">
            After resetting, you'll be automatically signed in to your account.
        </p>
    </div>
</div>
@endsection
