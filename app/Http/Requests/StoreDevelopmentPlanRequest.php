<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\EmployeeDevelopmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevelopmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageDevelopmentPlans->value);
    }

    public function rules(): array
    {
        return [
            'development_objective' => ['required', 'string', 'max:255'],
            'related_skill_id' => ['nullable', 'exists:skills,id'],
            'current_competency_level_id' => ['nullable', 'exists:competency_levels,id'],
            'target_competency_level_id' => ['nullable', 'exists:competency_levels,id'],
            'development_action' => ['required', Rule::in(array_keys(EmployeeDevelopmentPlan::ACTIONS))],
            'recommended_training_program_id' => ['nullable', 'exists:training_programs,id'],
            'mentor_employee_id' => ['nullable', 'exists:employees,id'],
            'target_completion_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(EmployeeDevelopmentPlan::PRIORITIES)],
            'status' => ['required', Rule::in(EmployeeDevelopmentPlan::STATUSES)],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'review_date' => ['nullable', 'date'],
            'manager_remarks' => ['nullable', 'string', 'max:2000'],
            'employee_remarks' => ['nullable', 'string', 'max:2000'],
            'completion_date' => ['nullable', 'date'],
        ];
    }
}
