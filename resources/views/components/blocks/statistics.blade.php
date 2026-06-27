<!-- Statistics Block -->
<section class="statistics-block py-12" style="background-color: {{ $background_color ?? '#f9fafb' }}">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach($stats ?? [] as $stat)
                <div class="text-center">
                    <div class="text-4xl font-bold mb-2" style="color: {{ $number_color ?? '#3b82f6' }}">
                        {{ $stat['number'] ?? '0' }}
                    </div>
                    <p class="text-gray-600 font-medium">{{ $stat['label'] ?? '' }}</p>
                    @if($stat['description'] ?? false)
                        <p class="text-sm text-gray-500 mt-2">{{ $stat['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
