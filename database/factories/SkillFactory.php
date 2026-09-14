<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Skill',
            'code' => fake()->unique()->regexify('SK-[A-Z]{5}'),
            'skill_category_id' => SkillCategory::factory(),
            'skill_type' => fake()->randomElement(['technical', 'soft']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
