<!-- Button Block -->
<section class="button-block py-8">
    <div class="container">
        <div class="flex justify-center">
            <a href="{{ $link ?? '#' }}" 
               class="inline-block px-8 py-3 rounded-lg font-semibold text-white transition-all hover:shadow-lg"
               style="background-color: {{ $button_color ?? '#3b82f6' }}">
                {{ $text ?? 'Click Here' }}
            </a>
        </div>
    </div>
</section>
