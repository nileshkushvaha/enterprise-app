{{-- Timeline Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative">
            <div class="absolute left-3.5 top-4 bottom-4 w-px bg-gradient-to-b from-indigo-400/60 via-violet-400/30 to-transparent"></div>
            @foreach($items ?? [] as $index => $item)
                <div class="relative flex gap-6 mb-10">
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="h-7 w-7 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/20 ring-4 ring-white">
                            <span class="text-[10px] font-bold text-white">{{ $index + 1 }}</span>
                        </div>
                    </div>
                    <div class="flex-1 pb-2">
                        <div class="flex items-baseline gap-3 mb-1">
                            <h4 class="font-semibold text-slate-900 text-base">{{ $item['title'] ?? '' }}</h4>
                            @if($item['date'] ?? false)
                                <span class="text-xs text-slate-500 font-medium">{{ $item['date'] }}</span>
                            @endif
                        </div>
                        @if($item['description'] ?? false)
                            <p class="text-sm text-slate-600 leading-relaxed">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
