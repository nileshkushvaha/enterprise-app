@extends('layouts.frontend')
@section('bare', true)

@section('title', 'Create Account — ' . config('app.name'))

@section('content')
<div class="auth-page" x-data="registerForm()">

    {{-- ── LEFT DECORATIVE PANEL ───────────────────────────────────────── --}}
    <div class="auth-left-panel justify-between p-10 xl:p-14">

        <div class="bg-orb w-[30rem] h-[30rem] bg-violet-600/20 top-[-10rem] right-[-8rem]"></div>
        <div class="bg-orb w-[24rem] h-[24rem] bg-indigo-600/15 bottom-[-8rem] left-[-6rem]" style="animation-delay:4s"></div>
        <div class="bg-orb w-[14rem] h-[14rem] bg-purple-500/10 top-[35%] left-[15%]" style="animation-delay:7s"></div>
        <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(139,92,246,.07) 1px,transparent 1px);background-size:36px 36px;"></div>

        <div class="relative z-10">
            {{-- Left panel logo → links to home --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3 mb-14 group w-fit">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 group-hover:shadow-indigo-500/50 transition-shadow">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">{{ config('app.name') }}</span>
            </a>

            {{-- Student count badge --}}
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/25 mb-6">
                <span class="badge-dot"></span>
                <span class="text-emerald-400 text-sm font-medium">10,000+ active students</span>
            </div>

            <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
                Start learning<br><span class="text-grad">for free</span> today
            </h1>
            <p class="text-slate-400 text-lg leading-relaxed mb-12">
                Join thousands of students already mastering new skills on {{ config('app.name') }}.
            </p>

            <div class="space-y-5">
                @foreach([
                    ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'Free to get started', 'desc' => 'No credit card required for basic access'],
                    ['icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'title' => '500+ Courses', 'desc' => 'From beginner to advanced level'],
                    ['icon' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z', 'title' => '24/7 Tutor Support', 'desc' => 'Get help whenever you need it'],
                    ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title' => 'Verified Certificates', 'desc' => 'Shareable credentials for your career'],
                ] as $feat)
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-violet-500/15 border border-violet-500/25 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5 text-violet-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        {{-- Stats row --}}
        <div class="relative z-10 mt-10 grid grid-cols-3 gap-4">
            @foreach([['10K+', 'Students'], ['500+', 'Courses'], ['98%', 'Satisfaction']] as $stat)
            <div class="text-center p-3 rounded-xl bg-white/[0.04] border border-white/[0.07]">
                <p class="text-xl font-bold text-white">{{ $stat[0] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $stat[1] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── RIGHT FORM PANEL ────────────────────────────────────────────── --}}
    <div class="auth-right-panel">
        <div class="absolute top-0 right-0 w-72 h-72 bg-violet-600/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-indigo-600/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 w-full max-w-md py-6">

            {{-- Mobile: logo + back to home --}}
            <div class="flex items-center justify-between mb-8 lg:hidden">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <span class="text-xl font-bold text-white">{{ config('app.name') }}</span>
                </a>
                <a href="{{ route('home') }}" class="text-xs text-slate-500 hover:text-slate-300 transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Home
                </a>
            </div>

            {{-- Desktop: back to home --}}
            <div class="hidden lg:flex justify-end mb-6">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-300 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to home
                </a>
            </div>

            <div class="mb-8">
                <h2 class="text-3xl font-bold text-white mb-2">Create account</h2>
                <p class="text-slate-400 text-sm">Already have one? <a href="{{ route('auth.login') }}" class="text-indigo-400 hover:text-indigo-300 font-medium transition">Sign in →</a></p>
            </div>

            @if(session('error'))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-red-300 text-sm">{{ session('error') }}</p>
            </div>
            @endif

            @if($errors->any() && !$errors->hasAny(['first_name','last_name','email','phone','password','password_confirmation','terms']))
            <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-500/10 border border-red-500/25 p-4">
                <svg class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-red-300 text-sm">Please fix the errors below.</p>
            </div>
            @endif

            <form method="POST" action="{{ route('auth.register') }}" @submit.prevent="submitForm($el)" class="space-y-4">
                @csrf

                {{-- Name row --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="first_name" class="auth-label">First name</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                            placeholder="John"
                            autocomplete="given-name"
                            class="auth-input @error('first_name') error @enderror" required>
                        @error('first_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="last_name" class="auth-label">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}"
                            placeholder="Doe"
                            autocomplete="family-name"
                            class="auth-input @error('last_name') error @enderror" required>
                        @error('last_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="auth-label">Email address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                        placeholder="you@example.com"
                        autocomplete="email"
                        class="auth-input @error('email') error @enderror" required>
                    @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Phone (optional) --}}
                <div>
                    <label for="phone" class="auth-label">
                        Phone number
                        <span class="text-slate-600 font-normal ml-1">(optional)</span>
                    </label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                        placeholder="+91 98765 43210"
                        autocomplete="tel"
                        class="auth-input @error('phone') error @enderror">
                    @error('phone')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="auth-label">Password</label>
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

                    {{-- 5-bar strength meter --}}
                    <div x-show="password.length > 0" class="mt-2.5">
                        <div class="flex gap-1.5 mb-1.5">
                            <template x-for="i in 5">
                                <div class="flex-1 h-1.5 rounded-full transition-all duration-300"
                                    :class="i <= strength
                                        ? (strength <= 1 ? 'bg-red-500' : strength <= 2 ? 'bg-amber-500' : strength <= 3 ? 'bg-yellow-400' : strength <= 4 ? 'bg-emerald-400' : 'bg-emerald-400')
                                        : 'bg-white/10'">
                                </div>
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
                    <label for="password_confirmation" class="auth-label">Confirm password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'"
                            id="password_confirmation" name="password_confirmation"
                            placeholder="Repeat your password"
                            autocomplete="new-password"
                            @input="checkMatch($event.target.value)"
                            class="auth-input pr-11">
                        <button type="button" @click="showConfirm = !showConfirm"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition focus:outline-none">
                            <svg x-show="!showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                        <div x-show="confirmVal.length > 0" class="absolute right-10 top-1/2 -translate-y-1/2">
                            <svg x-show="passwordsMatch" class="w-4.5 h-4.5 text-emerald-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <svg x-show="!passwordsMatch" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                    </div>
                    <p x-show="confirmVal.length > 0 && !passwordsMatch" class="mt-1 text-xs text-red-400">Passwords don't match</p>
                </div>

                {{-- Terms --}}
                <div class="flex items-start gap-3 pt-1">
                    <input type="checkbox" id="terms" name="terms"
                        class="mt-0.5 w-4 h-4 rounded bg-white/5 border border-white/15 text-indigo-500 focus:ring-indigo-500/30 focus:ring-2 cursor-pointer flex-shrink-0"
                        {{ old('terms') ? 'checked' : '' }} required>
                    <label for="terms" class="text-sm text-slate-400 cursor-pointer leading-snug">
                        I agree to the
                        <a href="#" class="text-indigo-400 hover:text-indigo-300 transition font-medium">Terms of Service</a>
                        and
                        <a href="#" class="text-indigo-400 hover:text-indigo-300 transition font-medium">Privacy Policy</a>
                    </label>
                </div>
                @error('terms')<p class="text-xs text-red-400 -mt-2">{{ $message }}</p>@enderror

                {{-- Submit --}}
                <div class="pt-1">
                    <button type="submit" class="auth-btn-primary" :disabled="loading || (confirmVal.length > 0 && !passwordsMatch)">
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-show="loading">Creating account…</span>
                        <span x-show="!loading" class="flex items-center gap-2">
                            Create free account
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </span>
                    </button>
                </div>

                <p class="text-center text-xs text-slate-600 mt-4">
                    Already have an account?
                    <a href="{{ route('auth.login') }}" class="text-slate-500 hover:text-slate-400 transition font-medium">Sign in instead</a>
                </p>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function registerForm() {
    return {
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
            this.strengthLabel = ['', 'Very weak', 'Weak', 'Fair', 'Strong', 'Very strong'][s] || '';
            if (this.confirmVal) this.checkMatch(this.confirmVal);
        },

        checkMatch(val) {
            this.confirmVal = val;
            this.passwordsMatch = val === this.password;
        },

        submitForm(form) {
            if (this.confirmVal && !this.passwordsMatch) return;
            this.loading = true;
            form.submit();
        }
    }
}
</script>
@endpush
@endsection
