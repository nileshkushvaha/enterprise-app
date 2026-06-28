{{--
    Site header chrome — logo, desktop nav, mobile drawer.
    Expects: $appName (string), $logo (?string)
    Works for both layouts/page.blade.php and layouts/frontend.blade.php
--}}
<header
    x-data="{ mobileOpen: false, scrolled: false }"
    @scroll.window="scrolled = (window.scrollY > 16)"
    :class="scrolled
        ? 'bg-[#05080F]/95 backdrop-blur-xl border-b border-white/[0.08] shadow-lg shadow-black/30'
        : 'bg-[#05080F]/70 backdrop-blur-sm border-b border-transparent'"
    class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            {{-- ── Logo ── --}}
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 flex-shrink-0 group">
                @if($logo ?? null)
                    <img src="{{ $logo }}" alt="{{ $appName }}" class="h-8 w-auto object-contain transition-opacity group-hover:opacity-90">
                @else
                    <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 transition-transform group-hover:scale-105">
                        <span class="text-white font-extrabold text-sm leading-none">{{ mb_substr($appName ?? 'E', 0, 1) }}</span>
                    </div>
                @endif
                <span class="text-white font-bold text-[1.0625rem] tracking-tight transition-colors group-hover:text-indigo-300">
                    {{ $appName }}
                </span>
            </a>

            {{-- ── Desktop Nav (centre) ── --}}
            <div class="hidden lg:flex items-center flex-1 justify-center px-8">
                <x-navigation location="header" />
            </div>

            {{-- ── Desktop Right actions ── --}}
            <div class="hidden lg:flex items-center gap-1">
                <a href="{{ route('search.index') }}"
                   class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-all"
                   aria-label="Search">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="height:1.125rem;width:1.125rem">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </a>
                @auth
                    @if(Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}"
                       class="ml-2 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition-all hover:shadow-indigo-500/30 hover:-translate-y-px">
                        Dashboard
                    </a>
                    @endif
                @else
                    @if(Route::has('login'))
                    <a href="{{ route('login') }}"
                       class="px-3 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white hover:bg-white/[0.06] transition-all">
                        Sign in
                    </a>
                    @endif
                    @if(Route::has('auth.register'))
                    <a href="{{ route('auth.register') }}"
                       class="ml-1 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition-all hover:shadow-indigo-500/30 hover:-translate-y-px">
                        Get Started
                    </a>
                    @endif
                @endauth
            </div>

            {{-- ── Mobile toggle ── --}}
            <button
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen ? 'true' : 'false'"
                aria-label="Toggle navigation"
                class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition-all"
            >
                <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

        </div>
    </div>

    {{-- ── Mobile drawer ── --}}
    <div
        x-show="mobileOpen"
        x-collapse
        x-cloak
        class="lg:hidden border-t border-white/[0.06]"
        style="background: rgba(5,8,15,.98); backdrop-filter: blur(20px);"
    >
        <div class="max-w-7xl mx-auto px-4 pb-4">
            <x-navigation location="mobile" />

            <div class="mt-4 pt-4 border-t border-white/[0.06] flex flex-col gap-2">
                @auth
                    @if(Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600">
                        Dashboard
                    </a>
                    @endif
                @else
                    @if(Route::has('login'))
                    <a href="{{ route('login') }}"
                       class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-300 border border-white/10">
                        Sign in
                    </a>
                    @endif
                    @if(Route::has('auth.register'))
                    <a href="{{ route('auth.register') }}"
                       class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600">
                        Get Started
                    </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</header>

{{-- Spacer so content clears the fixed header --}}
<div class="h-16"></div>
