<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_number', 'full_name', 'preferred_name', 'photo_path', 'gender', 'date_of_birth',
    'email', 'phone', 'department_id', 'division_id', 'maintenance_area_id', 'maintenance_team_id',
    'position_id', 'employment_type_id', 'employment_status_id', 'location_id', 'shift_id',
    'supervisor_id', 'manager_id', 'date_joined', 'education', 'technical_background',
    'years_of_experience', 'notes', 'user_id', 'created_by', 'updated_by',
])]
class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_joined' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function maintenanceArea(): BelongsTo
    {
        return $this->belongsTo(MaintenanceArea::class);
    }

    public function maintenanceTeam(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTeam::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Active means the employee's current employment status is configured
     * to count as active (see EmploymentStatus::counts_as_active) - never
     * inferred from the mere presence of a row.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('employmentStatus', fn (Builder $q) => $q->where('counts_as_active', true));
    }

    public function skillAssessments(): HasMany
    {
        return $this->hasMany(EmployeeSkillAssessment::class);
    }

    /**
     * The most recent assessment per skill, keyed by skill_id. Missing
     * assessments are simply absent from the collection - never treated as
     * an implicit competency level.
     */
    public function currentSkillAssessments(): \Illuminate\Support\Collection
    {
        return $this->skillAssessments()
            ->with(['skill', 'competencyLevel'])
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get()
            ->unique('skill_id')
            ->keyBy('skill_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('employee_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
