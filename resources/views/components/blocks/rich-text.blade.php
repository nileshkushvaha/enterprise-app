{{-- Rich Text Block --}}
<section class="py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white/80 rounded-3xl shadow-xl shadow-violet-100/40 border border-violet-100/60 px-6 sm:px-8 py-10 backdrop-blur-sm">
            <div class="prose prose-lg max-w-none
                prose-headings:text-slate-900 prose-headings:font-bold
                prose-p:text-slate-600 prose-p:leading-relaxed
                prose-a:text-violet-600 prose-a:no-underline hover:prose-a:text-violet-800
                prose-strong:text-slate-800
                prose-code:text-violet-700 prose-code:bg-violet-50 prose-code:rounded prose-code:px-1.5 prose-code:py-0.5
                prose-pre:bg-slate-900 prose-pre:border prose-pre:border-slate-800
                prose-blockquote:border-violet-400 prose-blockquote:text-slate-500 prose-blockquote:bg-violet-50/50 prose-blockquote:rounded-r-xl prose-blockquote:py-1
                prose-li:text-slate-600
                prose-hr:border-violet-100
                prose-img:rounded-2xl prose-img:shadow-lg"
                @if(($text_color ?? '') && $text_color !== '#000000')
                style="color: {{ $text_color }}"
                @endif
            >
                @if($text ?? false)
                    {!! $text !!}
                @endif
            </div>
        </div>
    </div>
</section>
