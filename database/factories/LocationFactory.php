<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Site',
            'code' => fake()->unique()->regexify('LOC-[A-Z]{3}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
