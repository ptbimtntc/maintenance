<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['employee_id', 'skill_id', 'competency_level_id', 'assessed_by', 'assessment_date', 'assessment_method', 'remarks'])]
class EmployeeSkillAssessment extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeSkillAssessmentFactory> */
    use HasFactory, SoftDeletes, Auditable;

    protected function casts(): array
    {
        return ['assessment_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function competencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
