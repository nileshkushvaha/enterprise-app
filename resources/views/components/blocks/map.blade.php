<!-- Map Block -->
<section class="map-block py-12">
    <div class="container">
        @if($title ?? false)
            <h2 class="text-2xl font-bold mb-6">{{ $title }}</h2>
        @endif
        <div class="rounded-lg overflow-hidden shadow-lg h-96">
            <iframe width="100%" 
                    height="100%" 
                    frameborder="0" 
                    src="https://www.google.com/maps/embed/v1/place?key=YOUR_GOOGLE_MAPS_API_KEY&q={{ urlencode($latitude ?? '0') }},{{ urlencode($longitude ?? '0') }}"
                    allowfullscreen="" 
                    loading="lazy">
            </iframe>
        </div>
    </div>
</section>
