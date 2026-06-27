<!-- Gallery Block -->
<section class="gallery-block py-12">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-{{ $columns ?? 3 }} lg:grid-cols-{{ $columns ?? 3 }} gap-{{ $gap ?? 6 }}">
            @foreach($images ?? [] as $galleryImage)
                <div class="gallery-item">
                    <a href="{{ $galleryImage['url'] ?? '' }}" class="gallery-link" data-lightbox="gallery">
                        <img src="{{ $galleryImage['url'] ?? '' }}" 
                             alt="{{ $galleryImage['caption'] ?? 'Gallery item' }}"
                             class="w-full h-64 object-cover rounded-lg hover:shadow-lg transition-shadow">
                    </a>
                    @if($galleryImage['caption'] ?? false)
                        <p class="mt-2 text-sm text-gray-600">{{ $galleryImage['caption'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
