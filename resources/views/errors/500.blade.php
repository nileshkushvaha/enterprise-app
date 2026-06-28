@extends('layouts.frontend')

@section('title', '500 — Server Error')
@section('meta_description', 'Something went wrong on our end.')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<main>
<div class="relative min-h-[85vh] flex items-center justify-center overflow-hidden" style="background: linear-gradient(135deg, #06080f 0%, #0e0b1f 40%, #08101e 100%)">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-32 right-0 w-96 h-96 bg-amber-600/8 rounded-full blur-3xl"></div>
        <div class="absolute bottom-16 -left-20 w-64 h-64 bg-indigo-700/8 rounded-full blur-3xl"></div>
    </div>
    <div class="relative text-center px-4 sm:px-6 animate-fade-in-up">
        <div class="mx-auto mb-6 h-16 w-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
            <svg class="h-8 w-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <p class="text-5xl font-black tracking-tight text-amber-500/30 select-none mb-2">500</p>
        <h1 class="text-2xl font-bold text-white">Something Went Wrong</h1>
        <p class="mt-3 text-slate-400 max-w-sm mx-auto text-sm leading-relaxed">
            We encountered an unexpected error. Our team has been notified. Please try again in a moment.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                Go Home
            </a>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 rounded-xl border border-white/[0.08] bg-white/[0.03] px-5 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/[0.06] transition-all cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                Try again
            </button>
        </div>
    </div>
</div>
</main>
@endsection
