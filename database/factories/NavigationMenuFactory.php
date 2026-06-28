<?php

namespace Database\Factories;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use App\Models\NavigationMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavigationMenu>
 */
class NavigationMenuFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true).' Menu';
        $slug = str($name)->slug('-')->toString();

        return [
            'name' => $name,
            'slug' => $slug,
            'location' => fake()->randomElement(NavigationLocation::cases())->value,
            'layout_type' => fake()->randomElement(NavigationLayoutType::cases())->value,
            'status' => NavigationStatus::Draft->value,
            'description' => fake()->optional(0.5)->sentence(),
            'locale' => null,
            'settings' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NavigationStatus::Published->value,
        ]);
    }

    public function forLocation(NavigationLocation $location): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $location->value,
        ]);
    }
}
