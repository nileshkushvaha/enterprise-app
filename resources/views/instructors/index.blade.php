@extends('layouts.frontend')

@section('title', 'Find an Instructor — ' . config('app.name'))

@push('meta')
    <meta name="description" content="Browse expert instructors on {{ config('app.name') }}. Filter by name, expertise, or experience.">
    <meta property="og:title" content="Find an Instructor — {{ config('app.name') }}">
    <meta property="og:description" content="Browse expert instructors on {{ config('app.name') }}.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('instructors.index') }}">
@endpush

@section('breadcrumbs')
    <x-frontend.breadcrumb :crumbs="[['label' => 'Instructors']]" />
@endsection

@section('content')
<div class="min-h-screen bg-[#05080F]">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        {{-- Page header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Our Instructors</h1>
            <p class="text-slate-400 mt-2">Learn from experienced professionals across a wide range of subjects.</p>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('instructors.index') }}" class="flex flex-col sm:flex-row gap-3 mb-8">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search by name or expertise…"
                class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
            >
            <select
                name="sort"
                class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-slate-300 focus:outline-none focus:border-indigo-500 transition"
            >
                <option value="featured" @selected(request('sort', 'featured') === 'featured')>Featured First</option>
                <option value="name" @selected(request('sort') === 'name')>Name A–Z</option>
                <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
            </select>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl transition">
                Search
            </button>
            @if(request('q') || (request('sort') && request('sort') !== 'featured'))
                <a href="{{ route('instructors.index') }}" class="px-4 py-2.5 text-slate-400 hover:text-white text-sm transition self-center">
                    Clear
                </a>
            @endif
        </form>

        {{-- Grid --}}
        @if($instructors->isEmpty())
            <div class="text-center py-20">
                <p class="text-slate-400 text-lg">No instructors found.</p>
                @if(request('q'))
                    <p class="text-slate-600 text-sm mt-2">Try a different search term.</p>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($instructors as $instructor)
                    <x-instructor.card :instructor="$instructor" />
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($instructors->hasPages())
                <div class="mt-10">
                    {{ $instructors->links() }}
                </div>
            @endif
        @endif

    </main>
</div>
@endsection
