@extends('layouts.frontend')
@section('title', 'Verify Your Email — ' . config('app.name'))

@section('content')
<div class="hero-mesh min-h-screen flex items-center justify-center py-16 px-4">
    <div class="max-w-md w-full relative z-10 text-center">

        <div class="glass rounded-3xl p-10 shadow-2xl shadow-indigo-500/10">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-4xl shadow-xl shadow-indigo-500/30">
                ✉️
            </div>

            <h1 class="text-2xl font-bold text-white mb-3">Check Your Email</h1>
            <p class="text-gray-400 text-sm leading-relaxed mb-6">
                We've sent a verification link to your email address.<br>
                Click the link to activate your account.
            </p>

            @if(session('success'))
                <div class="bg-emerald-500/15 border border-emerald-500/30 rounded-xl px-4 py-3 mb-6">
                    <p class="text-emerald-300 text-sm">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('resent'))
                <div class="bg-indigo-500/15 border border-indigo-500/30 rounded-xl px-4 py-3 mb-6">
                    <p class="text-indigo-300 text-sm">✓ Verification email resent. Please check your inbox.</p>
                </div>
            @endif

            <div class="bg-white/5 rounded-2xl p-5 mb-6 text-left space-y-3">
                <div class="flex items-start gap-3">
                    <span class="text-indigo-400 mt-0.5">1.</span>
                    <span class="text-gray-300 text-sm">Open your email inbox</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-indigo-400 mt-0.5">2.</span>
                    <span class="text-gray-300 text-sm">Find the email from <strong class="text-white">{{ config('app.name') }}</strong></span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-indigo-400 mt-0.5">3.</span>
                    <span class="text-gray-300 text-sm">Click <em class="text-white not-italic font-semibold">"Verify Email Address"</em></span>
                </div>
            </div>

            <p class="text-gray-500 text-xs mb-4">Didn't receive the email? Check your spam folder, or</p>

            <form method="POST" action="{{ route('auth.verification.resend') }}">
                @csrf
                <button type="submit" class="btn-indigo w-full py-3.5 rounded-2xl text-white font-semibold text-sm">
                    Resend Verification Email
                </button>
            </form>

            <a href="/" class="block mt-4 text-gray-500 hover:text-gray-300 text-sm transition-colors">
                ← Back to Home
            </a>
        </div>
    </div>
</div>
@endsection
