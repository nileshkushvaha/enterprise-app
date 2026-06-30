@props(['menu' => []])

<aside class="hidden lg:flex flex-col w-56 flex-shrink-0">
    <nav class="rounded-2xl border border-white/[0.07] p-3 sticky top-24" style="background:rgba(255,255,255,0.03)">

        {{-- User mini-card --}}
        <div class="flex items-center gap-2.5 px-3 py-2.5 mb-3 rounded-xl" style="background:rgba(255,255,255,0.04)">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center flex-shrink-0 overflow-hidden">
                @if(auth()->user()->avatar)
                    <img src="{{ asset('storage/' . auth()->user()->avatar) }}" class="w-full h-full object-cover" alt="">
                @else
                    <span class="text-white font-bold text-xs">{{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name, 0, 1)) }}</span>
                @endif
            </div>
            <div class="min-w-0">
                <p class="text-white text-xs font-semibold truncate">{{ auth()->user()->first_name ?? explode(' ', auth()->user()->name)[0] }}</p>
                <p class="text-slate-500 text-[10px] truncate">{{ auth()->user()->email }}</p>
            </div>
        </div>

        {{-- Nav items --}}
        @foreach($menu as $item)
            @php $active = request()->routeIs($item['route'] ?? ''); @endphp
            <a href="{{ $item['url'] }}"
               class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all mb-0.5
                      {{ $active
                           ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-500/25'
                           : 'text-slate-400 hover:text-white hover:bg-white/[0.05]' }}">
                @if(($item['icon'] ?? '') === 'home')
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                @elseif(($item['icon'] ?? '') === 'user')
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                @elseif(($item['icon'] ?? '') === 'book')
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                @else
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                @endif
                {{ $item['label'] }}
            </a>
        @endforeach

        {{-- Divider + Logout --}}
        <div class="mt-3 pt-3 border-t border-white/[0.06]">
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-500 hover:text-rose-400 hover:bg-rose-500/[0.06] transition-all">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign Out
                </button>
            </form>
        </div>
    </nav>
</aside>
