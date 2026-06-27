<!-- Map Block -->
<section class="map-block py-12">
    <div class="container">
        @if($title ?? false)
            <h2 class="text-2xl font-bold mb-6">{{ $title }}</h2>
        @endif
        <div class="rounded-lg overflow-hidden shadow-lg h-96">
            @php($googleMapsKey = config('services.google_maps.key'))
            @if($googleMapsKey)
                <iframe width="100%" 
                        height="100%" 
                        frameborder="0" 
                        src="https://www.google.com/maps/embed/v1/place?key={{ urlencode($googleMapsKey) }}&q={{ urlencode($latitude ?? '0') }},{{ urlencode($longitude ?? '0') }}&zoom={{ (int) ($zoom ?? 15) }}"
                        allowfullscreen="" 
                        loading="lazy">
                </iframe>
            @else
                <div class="flex h-full items-center justify-center bg-gray-100 text-gray-600">
                    Map preview unavailable. Configure GOOGLE_MAPS key in services configuration.
                </div>
                <span class="sr-only">{{ $latitude ?? '0' }},{{ $longitude ?? '0' }}</span>
            @endif
        </div>
    </div>
</section>
