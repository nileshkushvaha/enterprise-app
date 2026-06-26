@extends('layouts.frontend')

@section('title', 'Create Your Account — ' . config('app.name'))
@section('meta_description', 'Join thousands of students learning with expert tutors. Create your free account today.')

@section('content')

<div class="hero-mesh min-h-screen flex flex-col justify-center py-12 relative overflow-hidden">

    {{-- Background orbs --}}
    <div class="bg-orb w-[600px] h-[600px] top-[-200px] right-[-150px] opacity-15"
         style="background:radial-gradient(circle,#6366F1,transparent)"></div>
    <div class="bg-orb w-[400px] h-[400px] bottom-0 left-[-80px] opacity-10"
         style="background:radial-gradient(circle,#8B5CF6,transparent);animation-delay:3s;"></div>

    <div class="max-w-lg w-full mx-auto px-4 relative z-10" x-data="registerForm()">

        {{-- Logo + Back --}}
        <div class="flex items-center justify-between mb-8">
            <a href="/" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-white">{{ explode(' ', config('app.name'))[0] }}<span class="text-grad">Sphere</span></span>
            </a>
            <a href="/login" class="text-gray-400 hover:text-white text-sm transition-colors flex items-center gap-1">
                ← Back to Sign In
            </a>
        </div>

        {{-- Card --}}
        <div class="glass rounded-3xl p-8 shadow-2xl shadow-indigo-500/10">

            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Create Your Account</h1>
                <p class="text-gray-400 text-sm">Join 10,000+ students learning with expert tutors</p>
            </div>

            {{-- Session Flash --}}
            @if(session('success'))
                <div class="bg-emerald-500/15 border border-emerald-500/30 rounded-2xl px-5 py-4 mb-6 flex items-start gap-3">
                    <span class="text-emerald-400 text-lg mt-0.5">✓</span>
                    <p class="text-emerald-300 text-sm leading-relaxed">{{ session('success') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-500/15 border border-red-500/30 rounded-2xl px-5 py-4 mb-6">
                    <p class="text-red-400 font-semibold text-sm mb-2">Please fix the following errors:</p>
                    <ul class="space-y-1">
                        @foreach($errors->all() as $error)
                            <li class="text-red-300 text-xs flex items-start gap-2">
                                <span class="mt-0.5">•</span> {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('auth.register.store') }}" @submit="submitting = true" novalidate>
                @csrf

                {{-- Name Row --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                            First Name <span class="text-red-400">*</span>
                        </label>
                        <input
                            type="text"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            placeholder="John"
                            autocomplete="given-name"
                            class="w-full glass-md rounded-xl px-4 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all @error('first_name') ring-2 ring-red-500/50 @enderror"
                            required
                        >
                        @error('first_name')
                            <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">Last Name</label>
                        <input
                            type="text"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            placeholder="Doe"
                            autocomplete="family-name"
                            class="w-full glass-md rounded-xl px-4 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all @error('last_name') ring-2 ring-red-500/50 @enderror"
                        >
                        @error('last_name')
                            <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Email --}}
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                        Email Address <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </div>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="john@example.com"
                            autocomplete="email"
                            class="w-full glass-md rounded-xl pl-11 pr-4 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all @error('email') ring-2 ring-red-500/50 @enderror"
                            required
                        >
                    </div>
                    @error('email')
                        <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone --}}
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">Phone Number
                        <span class="text-gray-500 normal-case font-normal">(optional)</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                        </div>
                        <input
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="+91 98765 43210"
                            autocomplete="tel"
                            class="w-full glass-md rounded-xl pl-11 pr-4 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all @error('phone') ring-2 ring-red-500/50 @enderror"
                        >
                    </div>
                    @error('phone')
                        <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="mb-4" x-data="passwordStrength()">
                    <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                        Password <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                            </svg>
                        </div>
                        <input
                            :type="showPwd ? 'text' : 'password'"
                            name="password"
                            placeholder="Min. 12 characters"
                            autocomplete="new-password"
                            x-on:input="checkStrength($event.target.value)"
                            class="w-full glass-md rounded-xl pl-11 pr-11 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all @error('password') ring-2 ring-red-500/50 @enderror"
                            required
                        >
                        <button type="button" @click="showPwd = !showPwd" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors">
                            <svg x-show="!showPwd" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showPwd" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Strength Bar --}}
                    <div class="mt-2" x-show="password.length > 0" x-transition>
                        <div class="flex gap-1.5 mb-1.5">
                            <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                 :class="score >= 1 ? (score <= 2 ? 'bg-red-500' : score <= 3 ? 'bg-amber-400' : 'bg-emerald-500') : 'bg-white/10'"></div>
                            <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                 :class="score >= 2 ? (score <= 2 ? 'bg-red-500' : score <= 3 ? 'bg-amber-400' : 'bg-emerald-500') : 'bg-white/10'"></div>
                            <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                 :class="score >= 3 ? (score <= 3 ? 'bg-amber-400' : 'bg-emerald-500') : 'bg-white/10'"></div>
                            <div class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                 :class="score >= 4 ? 'bg-emerald-500' : 'bg-white/10'"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs transition-colors"
                               :class="score <= 2 ? 'text-red-400' : score <= 3 ? 'text-amber-400' : 'text-emerald-400'"
                               x-text="labels[score]"></p>
                            <div class="flex gap-2 flex-wrap justify-end">
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="checks.length ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-gray-600'">12+ chars</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="checks.upper ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-gray-600'">A-Z</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="checks.lower ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-gray-600'">a-z</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="checks.number ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-gray-600'">0-9</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="checks.symbol ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-gray-600'">#@!</span>
                            </div>
                        </div>
                    </div>
                    @error('password')
                        <p class="text-red-400 text-xs mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div class="mb-6" x-data="{ showConfirm: false }">
                    <label class="block text-gray-300 text-xs font-semibold mb-1.5 uppercase tracking-wide">
                        Confirm Password <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </div>
                        <input
                            :type="showConfirm ? 'text' : 'password'"
                            name="password_confirmation"
                            placeholder="Re-enter password"
                            autocomplete="new-password"
                            class="w-full glass-md rounded-xl pl-11 pr-11 py-3 text-white placeholder-gray-500 text-sm outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all"
                            required
                        >
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Terms --}}
                <div class="mb-7">
                    <label class="flex items-start gap-3 cursor-pointer group">
                        <div class="relative flex-shrink-0 mt-0.5">
                            <input type="checkbox" name="terms" value="1" class="sr-only peer" {{ old('terms') ? 'checked' : '' }}>
                            <div class="w-5 h-5 rounded-md border border-white/20 glass-md peer-checked:bg-indigo-500 peer-checked:border-indigo-500 transition-all flex items-center justify-center peer-checked:shadow-lg peer-checked:shadow-indigo-500/30">
                                <svg class="w-3 h-3 text-white hidden peer-checked:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-gray-400 text-sm leading-relaxed">
                            I agree to the
                            <a href="#" class="text-indigo-400 hover:text-indigo-300 underline underline-offset-2">Terms of Service</a>
                            and
                            <a href="#" class="text-indigo-400 hover:text-indigo-300 underline underline-offset-2">Privacy Policy</a>
                        </span>
                    </label>
                    @error('terms')
                        <p class="text-red-400 text-xs mt-1.5 ml-8">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    class="btn-amber w-full py-4 rounded-2xl text-white font-bold text-base relative overflow-hidden"
                    :disabled="submitting"
                    :class="submitting ? 'opacity-80 cursor-not-allowed' : ''"
                >
                    <span x-show="!submitting" class="flex items-center justify-center gap-2">
                        Create Account
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </span>
                    <span x-show="submitting" class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Creating your account…
                    </span>
                </button>
            </form>

            {{-- Divider --}}
            <div class="flex items-center gap-4 my-6">
                <div class="flex-1 h-px bg-white/10"></div>
                <span class="text-gray-500 text-xs">Already have an account?</span>
                <div class="flex-1 h-px bg-white/10"></div>
            </div>

            <a href="/login" class="block text-center glass-md py-3.5 rounded-2xl text-gray-300 hover:text-white font-semibold text-sm transition-all hover:bg-white/15">
                Sign In Instead
            </a>

        </div>

        {{-- Trust signals --}}
        <div class="flex items-center justify-center gap-5 mt-6 flex-wrap">
            @foreach(['🔒 256-bit SSL', '✅ Email Verified', '🚀 Free to Start'] as $badge)
                <div class="text-gray-500 text-xs flex items-center gap-1.5">{{ $badge }}</div>
            @endforeach
        </div>

    </div>

</div>

@push('scripts')
<script>
function registerForm() {
    return {
        submitting: false,
    }
}

function passwordStrength() {
    return {
        showPwd: false,
        password: '',
        score: 0,
        checks: { length: false, upper: false, lower: false, number: false, symbol: false },
        labels: ['', 'Too Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'],

        checkStrength(val) {
            this.password = val;
            this.checks = {
                length: val.length >= 12,
                upper:  /[A-Z]/.test(val),
                lower:  /[a-z]/.test(val),
                number: /[0-9]/.test(val),
                symbol: /[^A-Za-z0-9]/.test(val),
            };
            this.score = Object.values(this.checks).filter(Boolean).length;
        },
    }
}
</script>
@endpush

@endsection
