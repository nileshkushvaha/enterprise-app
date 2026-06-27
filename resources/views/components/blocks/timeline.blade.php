<!-- Timeline Block -->
<section class="timeline-block py-12">
    <div class="container max-w-2xl">
        <div class="relative">
            @foreach($items ?? [] as $index => $item)
                <div class="mb-8 flex gap-4">
                    <!-- Timeline dot -->
                    <div class="flex flex-col items-center">
                        <div class="w-4 h-4 bg-blue-500 rounded-full border-4 border-white shadow"></div>
                        @if(!$loop->last)
                            <div class="w-1 h-16 bg-blue-200 mt-2"></div>
                        @endif
                    </div>
                    
                    <!-- Timeline content -->
                    <div class="pt-1">
                        <h4 class="font-semibold text-lg">{{ $item['title'] ?? '' }}</h4>
                        <p class="text-sm text-gray-500">{{ $item['date'] ?? '' }}</p>
                        <p class="text-gray-700 mt-2">{{ $item['description'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
