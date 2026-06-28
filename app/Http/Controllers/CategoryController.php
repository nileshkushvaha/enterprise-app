<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    public function show(PostCategory $category): View
    {
        abort_unless($category->is_active, 404);

        $posts = Post::query()
            ->published()
            ->whereHas('categories', fn ($q) => $q->where('post_categories.id', $category->id))
            ->with(['author', 'media', 'categories', 'tags'])
            ->latest('published_at')
            ->paginate(12);

        return view('blog.category', [
            'category' => $category,
            'posts' => $posts,
        ]);
    }
}
