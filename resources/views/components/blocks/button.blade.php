{{-- Button Block --}}
<section class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-center">
            <a href="{{ $link ?? '#' }}"
               class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-px">
                {{ $text ?? 'Click Here' }}
            </a>
        </div>
    </div>
</section>
