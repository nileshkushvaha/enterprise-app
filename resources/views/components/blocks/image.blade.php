<!-- Image Block -->
<section class="image-block py-12">
    <div class="container">
        <figure>
            <img src="{{ $image ?? '' }}" 
                 alt="{{ $alt_text ?? 'Image' }}"
                 class="w-full rounded-lg shadow-lg">
            @if($caption ?? false)
                <figcaption class="text-center mt-4 text-gray-600">
                    {{ $caption }}
                </figcaption>
            @endif
        </figure>
    </div>
</section>
