<?php

namespace Database\Factories;

use App\Models\TrainingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingProvider>
 */
class TrainingProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->regexify('PRV-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
