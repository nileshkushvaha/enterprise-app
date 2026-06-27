<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_resource_includes_users_with_posts(): void
    {
        $author = User::factory()->create();
        Post::factory()->for($author, 'author')->create();

        $ids = AuthorResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($author->id));
    }

    public function test_author_resource_excludes_users_without_posts_or_author_role(): void
    {
        $user = User::factory()->create();

        $ids = AuthorResource::getEloquentQuery()->pluck('id');

        $this->assertFalse($ids->contains($user->id));
    }
}

