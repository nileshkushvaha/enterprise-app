{{-- Map Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($title ?? false)
            <h2 class="text-2xl font-bold text-slate-900 mb-6">{{ $title }}</h2>
        @endif
        <div class="rounded-2xl overflow-hidden border border-white/70 shadow-lg shadow-slate-200/50" style="height: 24rem">
            @php($googleMapsKey = config('services.google_maps.key'))
            @if($googleMapsKey)
                <iframe width="100%" height="100%" frameborder="0" loading="lazy"
                        src="https://www.google.com/maps/embed/v1/place?key={{ urlencode($googleMapsKey) }}&q={{ urlencode($latitude ?? '0') }},{{ urlencode($longitude ?? '0') }}&zoom={{ (int) ($zoom ?? 15) }}"
                        allowfullscreen>
                </iframe>
            @else
                <div class="flex h-full items-center justify-center bg-slate-50 text-slate-500 text-sm">
                    <div class="text-center">
                        <svg class="h-8 w-8 mx-auto mb-2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
                        Map unavailable — configure GOOGLE_MAPS key
                        <span class="sr-only">{{ $latitude ?? '0' }},{{ $longitude ?? '0' }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
