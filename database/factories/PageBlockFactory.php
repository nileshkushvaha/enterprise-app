<?php

namespace Database\Factories;

use App\Models\PageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageBlock>
 */
class PageBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blockType = fake()->randomElement(['hero', 'rich_text', 'image', 'gallery', 'video', 'cta', 'faq', 'accordion']);

        return [
            'page_id' => \App\Models\Page::factory(),
            'block_type' => $blockType,
            'content' => $this->generateContentForBlockType($blockType),
            'settings' => [
                'background_color' => fake()->optional(0.7)->hexColor(),
                'text_alignment' => fake()->randomElement(['left', 'center', 'right']),
                'padding' => fake()->randomElement(['small', 'medium', 'large']),
            ],
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => fake()->boolean(90),
        ];
    }

    private function generateContentForBlockType(string $blockType): array
    {
        return match ($blockType) {
            'hero' => [
                'title' => fake()->sentence(6),
                'subtitle' => fake()->sentence(8),
                'image' => fake()->imageUrl(1200, 600),
                'button_text' => 'Get Started',
                'button_link' => fake()->url(),
            ],
            'rich_text' => [
                'text' => fake()->paragraphs(3, true),
            ],
            'image' => [
                'image' => fake()->imageUrl(600, 400),
                'caption' => fake()->sentence(10),
                'alt_text' => fake()->sentence(5),
            ],
            'gallery' => [
                'images' => collect(range(1, 4))->map(fn () => [
                    'url' => fake()->imageUrl(400, 300),
                    'caption' => fake()->sentence(6),
                ])->toArray(),
                'columns' => fake()->randomElement([2, 3, 4]),
            ],
            'video' => [
                'url' => fake()->url(),
                'title' => fake()->sentence(5),
                'description' => fake()->paragraph(),
            ],
            'cta' => [
                'title' => fake()->sentence(6),
                'description' => fake()->paragraph(),
                'button_text' => 'Learn More',
                'button_link' => fake()->url(),
                'button_style' => fake()->randomElement(['primary', 'secondary']),
            ],
            'faq' => [
                'items' => collect(range(1, 3))->map(fn () => [
                    'question' => fake()->sentence(10),
                    'answer' => fake()->paragraph(2),
                ])->toArray(),
            ],
            'accordion' => [
                'items' => collect(range(1, 4))->map(fn () => [
                    'title' => fake()->sentence(6),
                    'content' => fake()->paragraph(),
                ])->toArray(),
            ],
            default => ['text' => fake()->sentence(10)],
        };
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => $type,
            'content' => $this->generateContentForBlockType($type),
        ]);
    }

    public function forPage(\App\Models\Page $page): static
    {
        return $this->state(fn (array $attributes) => [
            'page_id' => $page->id,
        ]);
    }
}
