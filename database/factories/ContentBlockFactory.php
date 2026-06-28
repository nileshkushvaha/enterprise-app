<?php

namespace Database\Factories;

use App\Content\Contracts\HasContentBlocks;
use App\Content\Models\ContentBlock;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentBlock>
 */
class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    public function definition(): array
    {
        $blockType = fake()->randomElement(['hero', 'rich_text', 'image', 'gallery', 'video', 'cta', 'faq', 'accordion']);

        return [
            'blockable_type' => (new Page)->getMorphClass(),
            'blockable_id' => Page::factory(),
            'block_type' => $blockType,
            'content' => $this->generateContent($blockType),
            'settings' => [
                'background_color' => fake()->optional(0.7)->hexColor(),
                'text_alignment' => fake()->randomElement(['left', 'center', 'right']),
                'padding' => fake()->randomElement(['small', 'medium', 'large']),
            ],
            'sort_order' => fake()->numberBetween(0, 10),
            'position' => 'after_content',
            'is_active' => fake()->boolean(90),
        ];
    }

    public function forOwner(HasContentBlocks $owner): static
    {
        return $this->state(fn () => [
            'blockable_type' => $owner->getMorphClass(),
            'blockable_id' => $owner->getKey(),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => [
            'block_type' => $type,
            'content' => $this->generateContent($type),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    private function generateContent(string $blockType): array
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
}
