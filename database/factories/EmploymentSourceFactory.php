<?php

namespace Database\Factories;

use App\Models\EmploymentSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentSource>
 */
class EmploymentSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->regexify('[A-Z]{2}[0-9]{2}'),
            'is_active' => true,
        ];
    }
}
