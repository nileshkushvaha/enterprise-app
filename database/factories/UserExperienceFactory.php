<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserExperience>
 */
class UserExperienceFactory extends Factory
{
    protected $model = UserExperience::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-8 years', '-1 year');

        return [
            'user_id' => User::factory(),
            'organization_name' => fake()->company(),
            'designation' => fake()->jobTitle(),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'industry' => fake()->words(2, true),
            'location' => fake()->city(),
            'skills' => fake()->words(3),
            'website' => fake()->url(),
            'is_current' => false,
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, 'now'),
            'display_order' => 0,
            'status' => 'active',
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_current' => true,
            'end_date' => null,
        ]);
    }
}
