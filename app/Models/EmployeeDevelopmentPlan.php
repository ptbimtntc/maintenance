<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_id', 'development_objective', 'related_skill_id', 'current_competency_level_id',
    'target_competency_level_id', 'development_action', 'recommended_training_program_id',
    'mentor_employee_id', 'target_completion_date', 'priority', 'status', 'progress_percentage',
    'review_date', 'manager_remarks', 'employee_remarks', 'completion_date', 'created_by',
])]
class EmployeeDevelopmentPlan extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeDevelopmentPlanFactory> */
    use HasFactory, SoftDeletes, Auditable;

    public const ACTIONS = [
        'formal_training' => 'Formal Training',
        'ojt' => 'On-the-Job Training',
        'coaching' => 'Coaching',
        'mentoring' => 'Mentoring',
        'job_rotation' => 'Job Rotation',
        'self_learning' => 'Self-learning',
        'practical_assessment' => 'Practical Assessment',
        'cross_training' => 'Cross-training',
        'certification' => 'Certification Program',
    ];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['not_started', 'in_progress', 'completed', 'on_hold'];

    protected function casts(): array
    {
        return [
            'target_completion_date' => 'date',
            'review_date' => 'date',
            'completion_date' => 'date',
            'progress_percentage' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function relatedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'related_skill_id');
    }

    public function currentCompetencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class, 'current_competency_level_id');
    }

    public function targetCompetencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class, 'target_competency_level_id');
    }

    public function recommendedTrainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'recommended_training_program_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'mentor_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
