<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\User;
use App\Models\UserEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserEducation>
 */
class UserEducationFactory extends Factory
{
    protected $model = UserEducation::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-10 years', '-2 years');

        return [
            'user_id' => User::factory(),
            'institution_name' => fake()->company().' University',
            'degree' => fake()->words(2, true),
            'field_of_study' => fake()->words(2, true),
            'education_level' => fake()->randomElement(EducationLevel::cases()),
            'grade' => fake()->randomElement(['A', 'A+', 'B', 'B+']),
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
