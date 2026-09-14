<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position_id', 'skill_id', 'required_competency_level_id', 'notes'])]
class PositionSkillRequirement extends Model
{
    /** @use HasFactory<\Database\Factories\PositionSkillRequirementFactory> */
    use HasFactory;

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function requiredCompetencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class, 'required_competency_level_id');
    }
}
