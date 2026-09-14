<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageEmployees->value);
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employeeId)],
            'full_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'maintenance_area_id' => ['nullable', 'exists:maintenance_areas,id'],
            'maintenance_team_id' => ['nullable', 'exists:maintenance_teams,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employeeId])],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employeeId])],
            'date_joined' => ['nullable', 'date'],
            'education' => ['nullable', 'string', 'max:100'],
            'technical_background' => ['nullable', 'string', 'max:255'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employeeId)],
        ];
    }
}
