<!-- Rich Text Block -->
<section class="rich-text-block py-12">
    <div class="container">
        <div class="prose max-w-none" style="color: {{ $text_color ?? '#000000' }}">
            @if($text ?? false)
                {!! nl2br(e($text)) !!}
            @endif
        </div>
    </div>
</section>
