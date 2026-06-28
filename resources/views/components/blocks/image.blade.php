{{-- Image Block --}}
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <figure>
            <div class="overflow-hidden rounded-2xl border border-white/70 shadow-lg shadow-slate-200/50">
                <img src="{{ $image ?? '' }}"
                     alt="{{ $alt_text ?? 'Image' }}"
                     class="w-full h-auto">
            </div>
            @if($caption ?? false)
                <figcaption class="text-center mt-3 text-sm text-slate-500">{{ $caption }}</figcaption>
            @endif
        </figure>
    </div>
</section>
