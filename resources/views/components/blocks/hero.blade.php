<!-- Hero Block: Large banner with title, subtitle, image, and CTA -->
<section class="hero-block py-20 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <!-- Content -->
            <div>
                @if($title ?? false)
                    <h1 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900">
                        {{ $title }}
                    </h1>
                @endif
                @if($subtitle ?? false)
                    <p class="text-lg text-gray-600 mb-6">
                        {{ $subtitle }}
                    </p>
                @endif
                @if(($button_text ?? false) && ($button_link ?? false))
                    <a href="{{ $button_link }}" 
                       class="inline-block px-8 py-3 rounded-lg font-semibold text-white bg-blue-500 hover:bg-blue-600">
                        {{ $button_text }}
                    </a>
                @endif
            </div>

            <!-- Image -->
            @if($image ?? false)
                <div>
                    <img src="{{ $image }}" 
                        alt="{{ $title ?? 'Hero' }}"
                        class="w-full rounded-lg shadow-lg">
                </div>
            @endif
        </div>
    </div>
</section>
