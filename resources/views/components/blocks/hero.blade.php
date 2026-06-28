{{-- Hero Block --}}
<section class="relative overflow-hidden py-20 lg:py-28" style="background: linear-gradient(135deg, #06080f 0%, #0e0b1f 40%, #08101e 100%)">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-32 right-0 w-[40rem] h-[40rem] bg-indigo-600/12 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 -left-20 w-80 h-80 bg-violet-700/8 rounded-full blur-3xl"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div>
                @if($title ?? false)
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight text-white leading-tight">
                        {{ $title }}
                    </h1>
                @endif
                @if($subtitle ?? false)
                    <p class="mt-6 text-lg text-slate-400 leading-relaxed">{{ $subtitle }}</p>
                @endif
                @if(($button_text ?? false) && ($button_link ?? false))
                    <div class="mt-8">
                        <a href="{{ $button_link }}"
                           class="inline-flex items-center gap-2 px-7 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-px">
                            {{ $button_text }}
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                @endif
            </div>
            @if($image ?? false)
                <div>
                    <img src="{{ $image }}" alt="{{ $title ?? 'Hero' }}" class="w-full rounded-2xl shadow-2xl shadow-black/40 border border-white/10">
                </div>
            @endif
        </div>
    </div>
</section>
