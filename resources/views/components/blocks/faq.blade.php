<!-- FAQ Block -->
<section class="faq-block py-12">
    <div class="container max-w-2xl">
        <div class="space-y-4" x-data>
            @foreach($items ?? [] as $index => $item)
                <details class="border rounded-lg overflow-hidden shadow">
                    <summary class="cursor-pointer px-6 py-4 font-semibold bg-gray-50 hover:bg-gray-100 transition">
                        {{ $item['question'] ?? '' }}
                    </summary>
                    <div class="px-6 py-4 bg-white text-gray-700">
                        {{ $item['answer'] ?? '' }}
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
