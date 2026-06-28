<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Rendering\ContentRenderer;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(): View
    {
        $posts = Post::query()
            ->published()
            ->with(['author', 'media', 'categories', 'tags'])
            ->latest('published_at')
            ->paginate(12);

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug, ContentRenderer $renderer): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with(['blocks' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->firstOrFail();

        return response($renderer->renderPost($post), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
