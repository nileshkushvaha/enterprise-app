<!-- Gallery Block -->
<section class="gallery-block py-12">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-{{ $columns ?? 3 }} lg:grid-cols-{{ $columns ?? 3 }} gap-{{ $gap ?? 6 }}">
            @foreach($images ?? [] as $galleryImage)
                @php
                    $imageUrl = is_array($galleryImage) ? ($galleryImage['url'] ?? '') : (string) $galleryImage;
                    $imageCaption = is_array($galleryImage) ? ($galleryImage['caption'] ?? null) : null;
                @endphp
                <div class="gallery-item">
                    <a href="{{ $imageUrl }}" class="gallery-link" data-lightbox="gallery">
                        <img src="{{ $imageUrl }}" 
                             alt="{{ $imageCaption ?? 'Gallery item' }}"
                             class="w-full h-64 object-cover rounded-lg hover:shadow-lg transition-shadow">
                    </a>
                    @if($imageCaption)
                        <p class="mt-2 text-sm text-gray-600">{{ $imageCaption }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
