<?php

namespace Database\Factories;

use App\Models\SkillPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillPosition>
 */
class SkillPositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->regexify('[A-Z]{2}[0-9]{2}'),
            'is_active' => true,
        ];
    }
}
