<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <main class="mx-auto max-w-5xl px-4 py-12">
        <h1 class="mb-8 text-4xl font-bold text-gray-900">Blog</h1>

        @if($posts->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-600">
                No published posts yet.
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                @foreach($posts as $post)
                    <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900">
                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600">
                                {{ $post->title }}
                            </a>
                        </h2>

                        <p class="mt-3 text-sm text-gray-600">{{ $post->excerpt }}</p>

                        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            <span>By {{ $post->author?->name ?? 'Unknown' }}</span>
                            <span>•</span>
                            <span>{{ max(1, (int) $post->reading_time) }} min read</span>
                            @if($post->published_at)
                                <span>•</span>
                                <span>{{ $post->published_at->format('M d, Y') }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $posts->links() }}
            </div>
        @endif
    </main>
</body>
</html>

