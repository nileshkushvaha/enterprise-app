{{--
    Site header — beautiful light glassmorphism with gradient accents.
    Expects: $appName (string), $logo (?string)
--}}
<header
    x-data="{ mobileOpen: false, scrolled: false }"
    @scroll.window="scrolled = (window.scrollY > 20)"
    class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
>
    {{-- ── Animated rainbow top line ── --}}
    <div class="h-[3px] w-full" style="background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899, #f59e0b, #10b981, #6366f1); background-size: 300% 100%; animation: gradientShift 4s ease infinite;"></div>

    {{-- ── Main bar ── --}}
    <div
        class="transition-all duration-300"
        :class="scrolled
            ? 'shadow-xl shadow-violet-100/60'
            : 'shadow-sm shadow-indigo-100/40'"
        style="background: rgba(255,255,255,0.82); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-bottom: 1px solid rgba(139,92,246,0.12);"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">

                {{-- ── Logo ── --}}
                <a href="{{ url('/') }}" class="flex items-center gap-3 flex-shrink-0 group">
                    @if($logo ?? null)
                        <img src="{{ $logo }}" alt="{{ $appName }}"
                             class="h-10 w-auto object-contain transition-all duration-300 group-hover:scale-105">
                    @else
                        <div class="relative h-10 w-10 flex-shrink-0">
                            <div class="absolute inset-0 rounded-2xl shadow-lg shadow-violet-200 transition-all duration-300 group-hover:shadow-violet-300 group-hover:scale-105"
                                 style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%)"></div>
                            <div class="absolute inset-0 rounded-2xl" style="background: linear-gradient(to bottom, rgba(255,255,255,0.25), transparent)"></div>
                            <div class="relative h-full flex items-center justify-center">
                                <span class="text-white font-black text-base leading-none tracking-tight">
                                    {{ mb_substr($appName ?? 'E', 0, 1) }}
                                </span>
                            </div>
                        </div>
                    @endif
                    <div class="flex flex-col leading-none">
                        <span class="font-extrabold text-[1.125rem] tracking-tight" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            {{ $appName }}
                        </span>
                        <span class="text-[0.6rem] font-semibold tracking-[0.15em] uppercase mt-0.5 text-violet-400">
                            Learning Platform
                        </span>
                    </div>
                </a>

                {{-- ── Desktop Nav ── --}}
                <div class="hidden lg:flex items-center flex-1 justify-center px-10">
                    <x-navigation location="header" />
                </div>

                {{-- ── Desktop Right actions ── --}}
                <div class="hidden lg:flex items-center gap-2">

                    {{-- Search --}}
                    <a href="{{ route('search.index') }}"
                       class="p-2.5 rounded-xl text-slate-400 hover:text-violet-600 hover:bg-violet-50 transition-all duration-200"
                       aria-label="Search">
                        <svg class="h-[1.1rem] w-[1.1rem]" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </a>

                    @auth
                        <x-account.profile-dropdown />
                    @else
                        @if(Route::has('auth.login'))
                        <a href="{{ route('auth.login') }}"
                           class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:text-violet-700 border border-violet-200 hover:border-violet-400 hover:bg-violet-50 transition-all duration-200">
                            Sign in
                        </a>
                        @endif
                        @if(Route::has('auth.register'))
                        <a href="{{ route('auth.register') }}"
                           class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold text-white btn-gradient">
                            Get Started
                            <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                        @endif
                    @endauth
                </div>

                {{-- ── Mobile toggle ── --}}
                <button
                    @click="mobileOpen = !mobileOpen"
                    :aria-expanded="mobileOpen ? 'true' : 'false'"
                    aria-label="Toggle navigation"
                    class="lg:hidden p-2.5 rounded-xl border border-violet-200 text-slate-500 hover:text-violet-700 hover:border-violet-400 hover:bg-violet-50 transition-all duration-200"
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
    </div>

    {{-- ── Mobile drawer ── --}}
    <div
        x-show="mobileOpen"
        x-collapse
        x-cloak
        class="lg:hidden border-t border-violet-100"
        style="background: rgba(255,255,255,0.96); backdrop-filter: blur(24px);"
    >
        <div class="max-w-7xl mx-auto px-4 pt-3 pb-5">
            <x-navigation location="mobile" />

            <div class="mt-4 pt-4 border-t border-violet-100 grid grid-cols-2 gap-3">
                @auth
                    @inject('portalResolver', 'App\Services\PortalResolver')
                    @if(Route::has('dashboard'))
                    <a href="{{ $portalResolver->dashboardRoute(auth()->user()) }}"
                       class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white btn-gradient">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                    @endif
                    <form method="POST" action="{{ route('auth.logout') }}" class="contents">
                        @csrf
                        <button type="submit"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-rose-600 border border-rose-200 hover:bg-rose-50 transition-all">
                            Sign Out
                        </button>
                    </form>
                @else
                    @if(Route::has('auth.login'))
                    <a href="{{ route('auth.login') }}"
                       class="flex items-center justify-center px-4 py-3 rounded-xl text-sm font-semibold text-slate-600 border border-violet-200">
                        Sign in
                    </a>
                    @endif
                    @if(Route::has('auth.register'))
                    <a href="{{ route('auth.register') }}"
                       class="flex items-center justify-center px-4 py-3 rounded-xl text-sm font-bold text-white btn-gradient">
                        Get Started →
                    </a>
                    @endif
                @endauth
            </div>

            <a href="{{ route('search.index') }}"
               class="mt-3 flex items-center gap-3 px-4 py-3 rounded-xl border border-violet-100 text-sm text-slate-400 hover:text-violet-600 hover:border-violet-200 transition-all">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                Search anything…
            </a>
        </div>
    </div>
</header>

{{-- Spacer: 3px accent line + 80px bar --}}
<div class="h-[83px]"></div>
