@extends('layouts.app')

@section('meta')
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    <meta name="robots" content="{{ $seo['robots'] ?? 'noindex, follow' }}">
    @if($seo['canonical'] ?? false)
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
@endsection

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-semibold mb-4">Search Pages</h1>

        <form action="{{ route('search.index') }}" method="GET" class="mb-6">
            <label for="q" class="sr-only">Search query</label>
            <input
                id="q"
                name="q"
                type="text"
                value="{{ $query }}"
                placeholder="Search by title, slug, or excerpt"
                class="w-full rounded border px-3 py-2"
            >
        </form>

        @if($query !== '')
            <p class="text-sm text-gray-600 mb-4">
                {{ $results->total() }} result(s) for "<strong>{{ $query }}</strong>"
            </p>
        @endif

        <div class="space-y-4">
            @forelse($results as $page)
                <article class="border rounded p-4">
                    <h2 class="text-xl font-medium">
                        <a class="hover:underline" href="{{ $page->slug === 'home' ? route('home') : route('page.show', $page->slug) }}">
                            {{ $page->title }}
                        </a>
                    </h2>
                    <p class="text-sm text-gray-500">/{{ $page->slug }}</p>
                    @if($page->excerpt)
                        <p class="mt-2 text-gray-700">{{ $page->excerpt }}</p>
                    @endif
                </article>
            @empty
                @if($query !== '')
                    <p class="text-gray-600">No pages matched your search.</p>
                @endif
            @endforelse
        </div>

        <div class="mt-6">
            {{ $results->links() }}
        </div>
    </div>
@endsection
