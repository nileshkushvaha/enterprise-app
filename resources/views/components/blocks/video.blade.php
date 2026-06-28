{{-- Video Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="aspect-video rounded-2xl overflow-hidden border border-white/70 shadow-lg shadow-slate-200/50 bg-slate-100">
            @php
                $url = $video_url ?? '';
                $isYoutube = str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be');
                $isVimeo   = str_contains($url, 'vimeo.com');
                if ($isYoutube) {
                    preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
                    $embedUrl = 'https://www.youtube.com/embed/' . ($m[1] ?? '');
                }
            @endphp
            @if($isYoutube)
                <iframe width="100%" height="100%" src="{{ $embedUrl }}" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            @elseif($isVimeo)
                <iframe src="{{ $url }}" width="100%" height="100%" frameborder="0"
                        allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            @elseif($url)
                <video width="100%" height="100%" controls class="w-full h-full">
                    <source src="{{ $url }}" type="video/mp4">
                </video>
            @endif
        </div>
        @if($caption ?? false)
            <p class="text-center mt-3 text-sm text-slate-500">{{ $caption }}</p>
        @endif
    </div>
</section>
