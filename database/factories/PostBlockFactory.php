<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostBlock>
 */
class PostBlockFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(BlockType::cases());

        return [
            'post_id' => Post::factory(),
            'block_type' => $type,
            'content' => match ($type) {
                BlockType::Hero => ['title' => fake()->sentence(4), 'subtitle' => fake()->sentence(8)],
                BlockType::RichText => ['text' => fake()->paragraph(4)],
                default => ['title' => fake()->sentence(4)],
            },
            'settings' => [],
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => fake()->boolean(90),
        ];
    }
}

