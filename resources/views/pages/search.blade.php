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
        <h1 class="text-2xl font-semibold mb-4">Search</h1>

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
                {{ $totalResults }} result(s) for "<strong>{{ $query }}</strong>"
            </p>
        @endif

        <section class="space-y-8">
            <div>
                <h2 class="text-lg font-semibold mb-3">Pages</h2>
                <div class="space-y-4">
                    @forelse($results['pages'] as $page)
                        <article class="border rounded p-4">
                            <h3 class="text-xl font-medium">
                                <a class="hover:underline" href="{{ $page->slug === 'home' ? route('home') : route('page.show', $page->slug) }}">
                                    {{ $page->title }}
                                </a>
                            </h3>
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
            </div>

            <div>
                <h2 class="text-lg font-semibold mb-3">Posts</h2>
                <div class="space-y-4">
                    @forelse($results['posts'] as $post)
                        <article class="border rounded p-4">
                            <h3 class="text-xl font-medium">
                                <a class="hover:underline" href="{{ route('blog.show', $post->slug) }}">
                                    {{ $post->title }}
                                </a>
                            </h3>
                            <p class="text-sm text-gray-500">/{{ $post->slug }}</p>
                            @if($post->excerpt)
                                <p class="mt-2 text-gray-700">{{ $post->excerpt }}</p>
                            @endif
                        </article>
                    @empty
                        @if($query !== '')
                            <p class="text-gray-600">No posts matched your search.</p>
                        @endif
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
