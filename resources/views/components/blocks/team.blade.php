{{-- Team Block --}}
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(($title ?? false) || ($description ?? false))
            <div class="text-center mb-12">
                @if($title ?? false)
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">{{ $title }}</h2>
                @endif
                @if($description ?? false)
                    <p class="text-lg text-slate-600 max-w-2xl mx-auto">{{ $description }}</p>
                @endif
            </div>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ $columns ?? 3 }} gap-6">
            @foreach($members ?? [] as $member)
                <div class="group rounded-2xl border border-white/70 bg-white/60 backdrop-blur-sm overflow-hidden transition-all hover:border-indigo-300/60 hover:shadow-lg shadow-sm">
                    @if($member['image'] ?? false)
                        <div class="overflow-hidden aspect-[4/3] bg-slate-100">
                            <img src="{{ $member['image'] }}"
                                 alt="{{ $member['name'] ?? '' }}"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        </div>
                    @else
                        <div class="aspect-[4/3] bg-gradient-to-br from-indigo-100 to-violet-100 flex items-center justify-center">
                            <div class="h-16 w-16 rounded-full bg-indigo-200 flex items-center justify-center">
                                <span class="text-2xl font-black text-indigo-600">{{ mb_substr($member['name'] ?? 'T', 0, 1) }}</span>
                            </div>
                        </div>
                    @endif
                    <div class="p-5">
                        <h3 class="text-base font-semibold text-slate-900">{{ $member['name'] ?? '' }}</h3>
                        @if($member['title'] ?? false)
                            <p class="text-sm text-indigo-600 font-medium mt-0.5">{{ $member['title'] }}</p>
                        @endif
                        @if($member['bio'] ?? false)
                            <p class="text-sm text-slate-600 mt-2 leading-relaxed">{{ $member['bio'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
