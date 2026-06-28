{{-- Statistics Block --}}
<section class="py-16 relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-300/30 to-transparent"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach($stats ?? [] as $stat)
                <div class="text-center">
                    <div class="text-4xl lg:text-5xl font-black text-indigo-600 mb-2">
                        {{ $stat['number'] ?? '0' }}
                    </div>
                    <p class="text-slate-800 font-semibold text-sm">{{ $stat['label'] ?? '' }}</p>
                    @if($stat['description'] ?? false)
                        <p class="text-slate-500 text-xs mt-1">{{ $stat['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-violet-300/30 to-transparent"></div>
</section>
