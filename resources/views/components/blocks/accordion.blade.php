<!-- Accordion Block -->
<section class="accordion-block py-12">
    <div class="container max-w-2xl">
        <div class="space-y-4">
            @foreach($items ?? [] as $index => $item)
                <details class="border rounded-lg overflow-hidden shadow">
                    <summary class="cursor-pointer px-6 py-4 font-semibold bg-gray-50 hover:bg-gray-100 transition flex items-center justify-between">
                        <span>{{ $item['title'] ?? '' }}</span>
                        <span class="text-gray-400">⊕</span>
                    </summary>
                    <div class="px-6 py-4 bg-white text-gray-700 border-t">
                        @if($item['content'] ?? false)
                            {!! nl2br(e($item['content'])) !!}
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
