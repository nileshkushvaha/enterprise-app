{{-- Testimonials Block --}}
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ $columns ?? 3 }} gap-6">
            @foreach($testimonials ?? [] as $testimonial)
                <div class="rounded-2xl border border-white/70 bg-white/60 backdrop-blur-sm p-6 flex flex-col shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-1 mb-4">
                        @for($i = 0; $i < ($testimonial['rating'] ?? 5); $i++)
                            <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                    <p class="text-slate-700 text-sm leading-relaxed italic flex-1">"{{ $testimonial['text'] ?? '' }}"</p>
                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-indigo-600">{{ mb_substr($testimonial['author'] ?? 'A', 0, 1) }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $testimonial['author'] ?? '' }}</p>
                            @if($testimonial['role'] ?? false)
                                <p class="text-xs text-slate-500">{{ $testimonial['role'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
