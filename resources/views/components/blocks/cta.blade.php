<!-- Call to Action Block -->
<section class="cta-block py-16" style="background-color: {{ $background_color ?? '#3b82f6' }}">
    <div class="container">
        <div class="max-w-2xl mx-auto text-center" style="color: {{ $text_color ?? '#ffffff' }}">
            @if($title ?? false)
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    {{ $title }}
                </h2>
            @endif
            @if($description ?? false)
                <p class="text-lg mb-8 opacity-90">
                    {{ $description }}
                </p>
            @endif
            @if(($button_text ?? false) && ($button_link ?? false))
                <a href="{{ $button_link }}" 
                   class="inline-block px-8 py-3 rounded-lg font-semibold"
                   style="background-color: #ffffff; color: #000000;">
                    {{ $button_text }}
                </a>
            @endif
        </div>
    </div>
</section>
