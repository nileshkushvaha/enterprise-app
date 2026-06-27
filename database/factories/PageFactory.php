<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);
        $slug = str($title)->slug('-')->toString();

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => fake()->paragraph(2),
            'template' => fake()->randomElement(['default', 'landing', 'blank']),
            'layout' => fake()->randomElement(['default', 'sidebar-left', 'sidebar-right', 'full-width']),
            'status' => fake()->randomElement(['draft', 'published', 'scheduled', 'archived']),
            'visibility' => fake()->randomElement(['public', 'private']),
            'published_at' => fake()->optional(0.7)->dateTimeBetween('-6 months', 'now'),
            'meta_title' => fake()->sentence(5),
            'meta_description' => fake()->text(160),
            'meta_keywords' => implode(', ', fake()->words(5)),
            'canonical_url' => fake()->optional(0.5)->url(),
            'robots' => 'index, follow',
            'created_by' => \App\Models\User::inRandomOrder()->first()?->id ?? 1,
            'updated_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'visibility' => 'private',
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'visibility' => 'public',
            'published_at' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'published_at' => now()->subMonths(3),
        ]);
    }
}
