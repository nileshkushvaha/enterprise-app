<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso2' => fake()->unique()->countryCode(),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'status' => 'active',
        ];
    }
}
