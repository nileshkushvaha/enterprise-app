<!-- Team Block -->
<section class="team-block py-12">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($members ?? [] as $member)
                <div class="team-member text-center">
                    @if($member['image'] ?? false)
                        <img src="{{ $member['image'] }}" 
                             alt="{{ $member['name'] ?? '' }}"
                             class="w-full h-64 object-cover rounded-lg mb-4">
                    @endif
                    <h3 class="text-xl font-semibold">{{ $member['name'] ?? '' }}</h3>
                    <p class="text-blue-600 font-medium">{{ $member['title'] ?? '' }}</p>
                    <p class="text-gray-600 mt-2">{{ $member['bio'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
