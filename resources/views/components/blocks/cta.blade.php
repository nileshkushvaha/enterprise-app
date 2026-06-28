{{-- Call to Action Block --}}
<section class="py-16 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none" style="background: linear-gradient(135deg, rgba(99,102,241,.07) 0%, rgba(139,92,246,.05) 100%)"></div>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-300/40 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-violet-300/40 to-transparent"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        @if($title ?? false)
            <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">{{ $title }}</h2>
        @endif
        @if($description ?? false)
            <p class="text-lg text-slate-600 mb-8 leading-relaxed max-w-2xl mx-auto">{{ $description }}</p>
        @endif
        @if(($button_text ?? false) && ($button_link ?? false))
            <a href="{{ $button_link }}"
               class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/25 transition-all hover:-translate-y-px">
                {{ $button_text }}
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        @endif
    </div>
</section>
