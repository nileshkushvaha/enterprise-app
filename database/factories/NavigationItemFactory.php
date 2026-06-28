<?php

namespace Database\Factories;

use App\Enums\Navigation\NavigationLinkType;
use App\Enums\Navigation\NavigationVisibility;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavigationItem>
 */
class NavigationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'navigation_id' => NavigationMenu::factory(),
            'parent_id' => null,
            'label' => fake()->words(2, true),
            'link_type' => NavigationLinkType::Url->value,
            'url' => fake()->url(),
            'route_name' => null,
            'route_params' => null,
            'linkable_type' => null,
            'linkable_id' => null,
            'target' => '_self',
            'rel' => null,
            'icon' => null,
            'css_class' => null,
            'css_id' => null,
            'badge_text' => null,
            'badge_color' => null,
            'visibility' => NavigationVisibility::All->value,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'open_in_modal' => false,
            'extra_attributes' => null,
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'link_type' => NavigationLinkType::External->value,
            'url' => fake()->url(),
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withBadge(string $text, string $color = 'success'): static
    {
        return $this->state(fn (array $attributes) => [
            'badge_text' => $text,
            'badge_color' => $color,
        ]);
    }
}
