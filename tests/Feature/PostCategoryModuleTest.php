<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCategoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_hierarchical_categories(): void
    {
        $parent = PostCategory::factory()->create(['name' => 'News']);
        $child = PostCategory::factory()->childOf($parent)->create(['name' => 'Announcements']);

        $this->assertTrue($child->parent->is($parent));
        $this->assertCount(1, $parent->children);
    }

    public function test_posts_can_attach_categories(): void
    {
        $post = Post::factory()->create();
        $category = PostCategory::factory()->create();

        $post->categories()->attach($category->id);

        $this->assertTrue($post->fresh()->categories->contains($category));
    }

    public function test_category_slug_is_unique_for_hierarchy(): void
    {
        $parent = PostCategory::factory()->create(['slug' => 'news']);
        $child = PostCategory::factory()->childOf($parent)->create(['slug' => 'news-local']);

        $this->assertNotEquals($parent->slug, $child->slug);
    }
}
