<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_id', 'training_program_id', 'training_session_id', 'training_date', 'training_type_id',
    'training_provider_id', 'trainer_name', 'duration_hours', 'attendance_status', 'completion_status',
    'assessment_score', 'assessment_result', 'competency_before_level_id', 'competency_after_level_id',
    'certificate_issued', 'certificate_reference', 'remarks', 'recorded_by', 'record_date',
])]
class TrainingRecord extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingRecordFactory> */
    use HasFactory, SoftDeletes;

    public const ATTENDANCE_STATUSES = ['attended', 'absent', 'excused'];

    public const COMPLETION_STATUSES = ['completed', 'incomplete', 'failed'];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'record_date' => 'date',
            'certificate_issued' => 'boolean',
            'duration_hours' => 'decimal:1',
            'assessment_score' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class);
    }

    public function trainingProvider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class);
    }

    public function competencyBeforeLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class, 'competency_before_level_id');
    }

    public function competencyAfterLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class, 'competency_after_level_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
