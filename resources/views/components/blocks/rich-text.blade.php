{{-- Rich Text Block --}}
<section class="py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="prose prose-invert prose-lg max-w-none
            prose-headings:text-white prose-headings:font-bold
            prose-p:text-slate-300 prose-p:leading-relaxed
            prose-a:text-indigo-400 prose-a:no-underline hover:prose-a:text-indigo-300
            prose-strong:text-white
            prose-code:text-indigo-300 prose-code:bg-white/[0.06] prose-code:rounded prose-code:px-1
            prose-pre:bg-white/[0.04] prose-pre:border prose-pre:border-white/[0.08]
            prose-blockquote:border-indigo-500 prose-blockquote:text-slate-400
            prose-li:text-slate-300
            prose-hr:border-white/[0.08]"
            @if(($text_color ?? '') && $text_color !== '#000000')
            style="color: {{ $text_color }}"
            @endif
        >
            @if($text ?? false)
                {!! $text !!}
            @endif
        </div>
    </div>
</section>
