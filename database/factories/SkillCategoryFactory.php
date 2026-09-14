<?php

namespace Database\Factories;

use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillCategory>
 */
class SkillCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Category',
            'code' => fake()->unique()->regexify('CAT-[A-Z]{4}'),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
