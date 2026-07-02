@props(['instructor'])

@php
    $profile = $instructor->profile;
    $avatarUrl = $profile?->avatarUrl;
    $coverUrl = $instructor->getFirstMediaUrl('instructor_cover');
    $currentPosition = $instructor->experiences()->active()->where('is_current', true)->first();
    $socialLinks = array_filter([
        'linkedin' => $profile?->linkedin,
        'github' => $profile?->github,
        'twitter' => $profile?->twitter,
        'website' => $profile?->website,
    ]);
@endphp

<a href="{{ route('instructors.show', $instructor) }}"
   class="group flex flex-col rounded-2xl border border-white/[0.07] overflow-hidden hover:border-indigo-500/50 hover:shadow-lg hover:shadow-indigo-500/10 transition-all duration-300"
   style="background:rgba(255,255,255,0.03)">

    {{-- Cover banner --}}
    <div class="h-24 bg-gradient-to-r from-indigo-600/40 to-violet-600/40 relative overflow-hidden">
        @if($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="w-full h-full object-cover opacity-60">
        @endif
    </div>

    <div class="p-5 flex flex-col flex-1 -mt-10">
        {{-- Avatar --}}
        <div class="flex items-end justify-between mb-4">
            <div class="w-16 h-16 rounded-full border-2 border-[#05080F] overflow-hidden bg-gradient-to-br from-indigo-500 to-violet-600 flex-shrink-0">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $instructor->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-white text-xl font-bold">
                        {{ strtoupper(substr($instructor->name, 0, 1)) }}
                    </div>
                @endif
            </div>
            @if($profile?->is_instructor_verified)
                <span class="flex items-center gap-1 text-xs text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded-full">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Verified
                </span>
            @endif
        </div>

        {{-- Name & title --}}
        <h3 class="text-white font-semibold text-base group-hover:text-indigo-300 transition line-clamp-1">
            {{ $instructor->name }}
        </h3>
        @if($currentPosition)
            <p class="text-slate-400 text-xs mt-0.5 line-clamp-1">
                {{ $currentPosition->designation }}@if($currentPosition->organization_name) · {{ $currentPosition->organization_name }}@endif
            </p>
        @elseif($profile?->headline)
            <p class="text-slate-400 text-xs mt-0.5 line-clamp-1">{{ $profile->headline }}</p>
        @endif

        {{-- Bio --}}
        @if($profile?->short_bio || $profile?->bio)
            <p class="text-slate-500 text-xs mt-3 line-clamp-2 flex-1">
                {{ $profile->short_bio ?: Str::limit($profile->bio, 100) }}
            </p>
        @else
            <div class="flex-1"></div>
        @endif

        {{-- Stats --}}
        <div class="flex items-center gap-4 mt-4 pt-4 border-t border-white/[0.06] text-xs text-slate-500">
            @php $yearsExp = round($instructor->experiences()->active()->get()->sum(fn($e) => (float) $e->start_date->diffInDays($e->end_date ?? now())) / 365, 1); @endphp
            @if($yearsExp > 0)
                <span>{{ $yearsExp }}y exp</span>
            @endif
            <span>0 courses</span>
        </div>

        {{-- Social links --}}
        @if($profile?->show_social_links && $socialLinks)
            <div class="flex items-center gap-3 mt-3">
                @foreach($socialLinks as $type => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                       class="text-slate-600 hover:text-indigo-400 transition text-xs"
                       onclick="event.preventDefault(); event.stopPropagation(); window.open('{{ $url }}', '_blank');">
                        {{ ucfirst($type) }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</a>
