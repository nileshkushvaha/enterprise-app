{{--
    Site footer chrome — nav columns, contact info, copyright.
    Expects: $appName, $logo, $footerText, $footerCopyright, $supportEmail, $supportPhone, $address
--}}
<footer class="relative border-t border-white/[0.06] overflow-hidden" style="background: rgba(5,8,15,.98)">

    {{-- Subtle gradient orb background --}}
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-indigo-900/20 rounded-full blur-3xl"></div>
        <div class="absolute -top-20 right-0 w-48 h-48 bg-violet-900/15 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10">

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">

            {{-- ── Brand column ── --}}
            <div class="lg:col-span-2 space-y-5">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 group">
                    @if($logo ?? null)
                        <img src="{{ $logo }}" alt="{{ $appName }}" class="h-8 w-auto object-contain">
                    @else
                        <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                            <span class="text-white font-extrabold text-sm leading-none">{{ mb_substr($appName ?? 'E', 0, 1) }}</span>
                        </div>
                    @endif
                    <span class="text-white font-bold text-lg tracking-tight">{{ $appName }}</span>
                </a>

                @if($footerText ?? null)
                    <p class="text-slate-400 text-sm leading-relaxed max-w-xs">{{ $footerText }}</p>
                @endif

                {{-- Contact snippets --}}
                <div class="space-y-2 pt-1">
                    @if($supportPhone ?? null)
                        <div class="flex items-center gap-2.5 text-sm text-slate-500">
                            <svg class="h-4 w-4 flex-shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                            <span>{{ $supportPhone }}</span>
                        </div>
                    @endif
                    @if($supportEmail ?? null)
                        <div class="flex items-center gap-2.5 text-sm text-slate-500">
                            <svg class="h-4 w-4 flex-shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            <a href="mailto:{{ $supportEmail }}" class="hover:text-slate-300 transition-colors">{{ $supportEmail }}</a>
                        </div>
                    @endif
                    @if($address ?? null)
                        <div class="flex items-start gap-2.5 text-sm text-slate-500">
                            <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                            <span class="leading-relaxed">{{ $address }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Navigation columns ── --}}
            <div class="lg:col-span-3">
                <x-navigation location="footer" />
            </div>
        </div>

        {{-- ── Copyright bar ── --}}
        <div class="mt-14 pt-8 border-t border-white/[0.04] flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-slate-600 text-center sm:text-left">
                {!! $footerCopyright ?? ('&copy; ' . date('Y') . ' ' . ($appName ?? config('app.name')) . '. All rights reserved.') !!}
            </p>
            <div class="flex items-center gap-5">
                <a href="{{ url('/privacy-policy') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Privacy Policy</a>
                <a href="{{ url('/terms-of-service') }}" class="text-xs text-slate-600 hover:text-slate-400 transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
