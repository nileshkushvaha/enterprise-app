<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTagModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_can_attach_tags(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();

        $post->tags()->attach($tag->id);

        $this->assertTrue($post->fresh()->tags->contains($tag));
    }

    public function test_tag_slug_is_unique(): void
    {
        $first = Tag::factory()->create(['slug' => 'laravel']);
        $second = Tag::factory()->create(['slug' => 'php']);

        $this->assertNotEquals($first->slug, $second->slug);
    }
}
