<!-- Video Block -->
<section class="video-block py-12">
    <div class="container max-w-4xl">
        <div class="aspect-video rounded-lg overflow-hidden shadow-lg">
            @if(strpos($video_url ?? '', 'youtube.com') !== false || strpos($video_url ?? '', 'youtu.be') !== false)
                <iframe width="100%" height="100%" 
                        src="{{ str_replace('watch?v=', 'embed/', $video_url) }}" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            @elseif(strpos($video_url ?? '', 'vimeo.com') !== false)
                <iframe src="{{ $video_url }}" width="100%" height="100%" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            @else
                <video width="100%" height="100%" controls>
                    <source src="{{ $video_url ?? '' }}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            @endif
        </div>
        @if($caption ?? false)
            <p class="text-center mt-4 text-gray-600">{{ $caption }}</p>
        @endif
    </div>
</section>
