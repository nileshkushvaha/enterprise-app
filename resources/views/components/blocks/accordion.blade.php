{{-- Accordion Block --}}
<section class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="space-y-3" x-data="{ open: null }">
            @foreach($items ?? [] as $index => $item)
                <div class="rounded-xl border border-white/[0.07] bg-white/[0.02] overflow-hidden transition-all hover:border-indigo-500/20">
                    <button
                        @click="open = (open === {{ $index }}) ? null : {{ $index }}"
                        class="w-full flex items-center justify-between px-6 py-4 text-left font-medium text-white hover:text-indigo-300 transition-colors"
                        :aria-expanded="open === {{ $index }}"
                    >
                        <span>{{ $item['title'] ?? '' }}</span>
                        <svg class="h-4 w-4 text-slate-500 transition-transform flex-shrink-0"
                             :class="{ 'rotate-180': open === {{ $index }} }"
                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    <div x-show="open === {{ $index }}" x-collapse x-cloak
                         class="px-6 pb-5 text-slate-400 text-sm leading-relaxed border-t border-white/[0.05]">
                        <div class="pt-4">
                            @if($item['content'] ?? false)
                                {!! nl2br(e($item['content'])) !!}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
