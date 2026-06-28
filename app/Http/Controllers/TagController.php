<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\View\View;

class TagController extends Controller
{
    public function show(Tag $tag): View
    {
        abort_unless($tag->is_active, 404);

        $posts = Post::query()
            ->published()
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->with(['author', 'media', 'categories', 'tags'])
            ->latest('published_at')
            ->paginate(12);

        return view('blog.tag', [
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }
}
