<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'excerpt' => fake()->paragraph(),
            'content' => fake()->optional(0.6)->randomHtml(),
            'author_id' => User::factory(),
            'status' => 'draft',
            'visibility' => 'private',
            'published_at' => null,
            'reading_time' => fake()->numberBetween(1, 8),
            'featured' => fake()->boolean(25),
            'allow_comments' => true,
            'meta_title' => fake()->optional()->sentence(6),
            'meta_description' => fake()->optional()->text(120),
            'meta_keywords' => fake()->optional()->words(5, true),
            'canonical_url' => fake()->optional()->url(),
            'robots' => 'index, follow',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subHour(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'visibility' => 'private',
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'scheduled',
            'visibility' => 'public',
            'published_at' => now()->addDay(),
        ]);
    }
}

