{{-- Gallery Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-{{ $columns ?? 3 }} gap-4">
            @foreach($images ?? [] as $galleryImage)
                @php
                    $imageUrl     = is_array($galleryImage) ? ($galleryImage['url'] ?? '') : (string) $galleryImage;
                    $imageCaption = is_array($galleryImage) ? ($galleryImage['caption'] ?? null) : null;
                @endphp
                <div class="group overflow-hidden rounded-xl border border-white/70 bg-white/60 backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                    <div class="overflow-hidden aspect-video">
                        <img src="{{ $imageUrl }}"
                             alt="{{ $imageCaption ?? 'Gallery image' }}"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                             loading="lazy">
                    </div>
                    @if($imageCaption)
                        <p class="px-3 py-2 text-xs text-slate-500">{{ $imageCaption }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
