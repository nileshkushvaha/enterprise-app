@extends('layouts.frontend')

@section('title', $user->name . ' — Instructor — ' . config('app.name'))

@push('meta')
    <meta name="description" content="{{ $profile->short_bio ?: Str::limit($profile->bio ?? $user->name . ' is an instructor on ' . config('app.name'), 160) }}">
    <meta property="og:title" content="{{ $user->name }} — Instructor">
    <meta property="og:description" content="{{ Str::limit($profile->bio ?? $user->name . ' is an instructor on ' . config('app.name'), 200) }}">
    <meta property="og:type" content="profile">
    <meta property="og:url" content="{{ route('instructors.show', $user) }}">
    @if($profile->avatarUrl)
        <meta property="og:image" content="{{ $profile->avatarUrl }}">
    @endif
    <meta property="profile:first_name" content="{{ $user->first_name ?: $user->name }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $user->name }} — Instructor">
    <link rel="canonical" href="{{ route('instructors.show', $user) }}">
@endpush

@push('structured_data')
@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $user->name,
        'url' => route('instructors.show', $user),
    ];
    if ($profile->bio) {
        $jsonLd['description'] = Str::limit(strip_tags($profile->bio), 500);
    }
    if ($profile->avatarUrl) {
        $jsonLd['image'] = $profile->avatarUrl;
    }
    if ($currentPosition) {
        $jsonLd['jobTitle'] = $currentPosition->designation;
        $jsonLd['worksFor'] = ['@type' => 'Organization', 'name' => $currentPosition->organization_name];
    }
@endphp
<script type="application/ld+json">{{ json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</script>
@endpush

@section('breadcrumbs')
    <x-frontend.breadcrumb :crumbs="[
        ['label' => 'Instructors', 'url' => route('instructors.index')],
        ['label' => $user->name],
    ]" />
@endsection

@section('content')
<div class="min-h-screen bg-[#05080F]">

    {{-- Hero / Cover --}}
    @php $coverUrl = $user->getFirstMediaUrl('instructor_cover'); @endphp
    <div class="relative h-48 sm:h-64 bg-gradient-to-r from-indigo-600/40 to-violet-600/40 overflow-hidden">
        @if($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="w-full h-full object-cover opacity-60">
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-[#05080F] via-transparent to-transparent"></div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 relative z-10 pb-16">

        {{-- Profile header --}}
        <div class="flex flex-col sm:flex-row sm:items-end gap-5 mb-8">
            <div class="w-24 h-24 rounded-full border-4 border-[#05080F] overflow-hidden bg-gradient-to-br from-indigo-500 to-violet-600 flex-shrink-0">
                @if($profile->avatarUrl)
                    <img src="{{ $profile->avatarUrl }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-white text-3xl font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $user->name }}</h1>
                    @if($profile->is_instructor_verified)
                        <span class="flex items-center gap-1 text-xs text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Verified Instructor
                        </span>
                    @endif
                </div>
                @if($currentPosition)
                    <p class="text-slate-400 mt-1">
                        {{ $currentPosition->designation }}
                        @if($currentPosition->organization_name)
                            <span class="text-slate-600">·</span> {{ $currentPosition->organization_name }}
                        @endif
                    </p>
                @elseif($profile->headline)
                    <p class="text-slate-400 mt-1">{{ $profile->headline }}</p>
                @endif
            </div>
        </div>

        {{-- Stat bar --}}
        <div class="mb-8">
            <x-instructor.stat-bar :stats="$stats" />
        </div>

        {{-- Main content grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left column --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Bio --}}
                @if($profile->bio)
                    <x-account.card title="About">
                        <p class="text-slate-300 text-sm leading-relaxed whitespace-pre-line">{{ $profile->bio }}</p>
                    </x-account.card>
                @endif

                {{-- Experience --}}
                <x-account.experience-timeline :experiences="$experiences" />

                {{-- Education --}}
                <x-account.education-timeline :educations="$educations" />

                {{-- Skills --}}
                @if($skills->isNotEmpty())
                    <x-account.card title="Skills">
                        <div class="flex flex-wrap gap-2">
                            @foreach($skills as $skill)
                                <span class="px-3 py-1 text-xs text-indigo-300 bg-indigo-500/10 border border-indigo-500/20 rounded-full">
                                    {{ $skill }}
                                </span>
                            @endforeach
                        </div>
                    </x-account.card>
                @endif

                {{-- Certificates --}}
                @if($certificates->isNotEmpty())
                    <x-account.card title="Certificates">
                        <div class="space-y-3">
                            @foreach($certificates as $cert)
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-white text-sm font-medium">{{ $cert->degree ?? $cert->education_level?->label() }}</p>
                                        <p class="text-slate-400 text-xs">{{ $cert->institution_name }}</p>
                                        @if($cert->certificate_number)
                                            <p class="text-slate-600 text-xs mt-0.5">Cert # {{ $cert->certificate_number }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-account.card>
                @endif

                {{-- Courses — stubbed until Course model exists --}}
                <x-account.card title="Courses">
                    <div class="text-center py-8">
                        <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        <p class="text-slate-500 text-sm">Courses coming soon.</p>
                    </div>
                </x-account.card>

                {{-- Reviews — stubbed --}}
                <x-account.card title="Reviews">
                    <div class="text-center py-8">
                        <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <p class="text-slate-500 text-sm">No reviews yet.</p>
                    </div>
                </x-account.card>
            </div>

            {{-- Right column --}}
            <div class="space-y-6">

                {{-- Links --}}
                @if($profile->show_social_links && ($profile->website || $profile->linkedin || $profile->github || $profile->twitter || $profile->facebook || $profile->instagram || $profile->youtube))
                    <x-account.card title="Links">
                        <div class="space-y-2">
                            @foreach(['website' => 'Website', 'linkedin' => 'LinkedIn', 'github' => 'GitHub', 'twitter' => 'Twitter', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $field => $label)
                                @if($profile->{$field})
                                    <a href="{{ $profile->{$field} }}" target="_blank" rel="noopener noreferrer"
                                       class="block text-sm text-indigo-400 hover:text-indigo-300 transition">{{ $label }}</a>
                                @endif
                            @endforeach
                        </div>
                    </x-account.card>
                @endif

                {{-- Related instructors --}}
                @if($related->isNotEmpty())
                    <div>
                        <h2 class="text-base font-semibold text-white mb-4">Other Instructors</h2>
                        <div class="space-y-3">
                            @foreach($related as $rel)
                                @php
                                    $relProfile = $rel->profile;
                                    $relPos = $rel->experiences()->active()->where('is_current', true)->first();
                                @endphp
                                <a href="{{ route('instructors.show', $rel) }}"
                                   class="flex items-center gap-3 p-3 rounded-xl border border-white/[0.06] hover:border-indigo-500/30 transition"
                                   style="background:rgba(255,255,255,0.02)">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex-shrink-0 overflow-hidden">
                                        @if($relProfile?->avatarUrl)
                                            <img src="{{ $relProfile->avatarUrl }}" alt="{{ $rel->name }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-white text-sm font-bold">
                                                {{ strtoupper(substr($rel->name, 0, 1)) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-white text-sm font-medium truncate">{{ $rel->name }}</p>
                                        @if($relPos)
                                            <p class="text-slate-500 text-xs truncate">{{ $relPos->designation }}</p>
                                        @elseif($relProfile?->headline)
                                            <p class="text-slate-500 text-xs truncate">{{ $relProfile->headline }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <a href="{{ route('instructors.index') }}" class="block text-center text-xs text-indigo-400 hover:text-indigo-300 mt-4 transition">
                            View all instructors →
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </main>
</div>
@endsection
