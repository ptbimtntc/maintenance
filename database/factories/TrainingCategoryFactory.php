<?php

namespace Database\Factories;

use App\Models\TrainingCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCategory>
 */
class TrainingCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Training',
            'code' => fake()->unique()->regexify('TC-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
