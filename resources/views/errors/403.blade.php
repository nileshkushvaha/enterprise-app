@extends('layouts.frontend')

@section('title', '403 — Forbidden')
@section('meta_description', 'You do not have permission to access this page.')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<main>
<div class="relative min-h-[85vh] flex items-center justify-center overflow-hidden" style="background: linear-gradient(135deg, #06080f 0%, #110d20 40%, #08101e 100%)">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-32 right-0 w-96 h-96 bg-red-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-16 -left-20 w-64 h-64 bg-violet-700/8 rounded-full blur-3xl"></div>
    </div>
    <div class="relative text-center px-4 sm:px-6 animate-fade-in-up">
        <div class="mx-auto mb-6 h-16 w-16 rounded-2xl bg-red-500/10 border border-red-500/20 flex items-center justify-center">
            <svg class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        </div>
        <p class="text-5xl font-black tracking-tight text-red-500/40 select-none mb-2">403</p>
        <h1 class="text-2xl font-bold text-white">Access Denied</h1>
        <p class="mt-3 text-slate-400 max-w-sm mx-auto text-sm leading-relaxed">
            {{ $exception->getMessage() ?: 'You do not have permission to view this page.' }}
        </p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                Go Home
            </a>
            @auth
            <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 rounded-xl border border-white/[0.08] bg-white/[0.03] px-5 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/[0.06] transition-all">
                Go back
            </a>
            @elseif(Route::has('login'))
            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/[0.08] bg-white/[0.03] px-5 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/[0.06] transition-all">
                Sign in
            </a>
            @endauth
        </div>
    </div>
</div>
</main>
@endsection
