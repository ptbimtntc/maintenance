<?php

namespace Database\Factories;

use App\Models\CompetencyLevel;
use App\Models\Position;
use App\Models\PositionSkillRequirement;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PositionSkillRequirement>
 */
class PositionSkillRequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'skill_id' => Skill::factory(),
            'required_competency_level_id' => CompetencyLevel::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
