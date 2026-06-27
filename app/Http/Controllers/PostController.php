<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PageRenderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(): View
    {
        $posts = Post::query()
            ->published()
            ->with('author')
            ->latest('published_at')
            ->paginate(12);

        return view('blog.index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $slug, PageRenderService $renderService): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'author',
                'blocks' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();

        if ($post->published_at && $post->published_at->isFuture()) {
            abort(404);
        }

        return response($renderService->renderPost($post), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

