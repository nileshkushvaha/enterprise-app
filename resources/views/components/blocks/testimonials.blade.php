<!-- Testimonials Block -->
<section class="testimonials-block py-12">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($testimonials ?? [] as $testimonial)
                <div class="testimonial bg-gray-50 p-6 rounded-lg">
                    <div class="flex items-center gap-1 mb-4">
                        @for($i = 0; $i < ($testimonial['rating'] ?? 5); $i++)
                            <span class="text-yellow-400">★</span>
                        @endfor
                    </div>
                    <p class="text-gray-700 mb-4 italic">"{{ $testimonial['text'] ?? '' }}"</p>
                    <div class="border-t pt-4">
                        <p class="font-semibold">{{ $testimonial['author'] ?? '' }}</p>
                        <p class="text-sm text-gray-600">{{ $testimonial['role'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
