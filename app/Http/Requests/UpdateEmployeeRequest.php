<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\Employee;
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
            'lototo_number' => ['nullable', 'string', 'max:50'],
            'sap_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'sap_id')->ignore($employeeId)],
            'license_number' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'business_unit_id' => ['nullable', 'exists:business_units,id'],
            'maintenance_team_id' => ['nullable', 'exists:maintenance_teams,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'skill_position_id' => ['nullable', 'exists:skill_positions,id'],
            'employment_type_id' => ['nullable', 'exists:employment_types,id'],
            'employment_source_id' => ['nullable', 'exists:employment_sources,id'],
            'workforce_category' => ['nullable', Rule::in(array_keys(Employee::WORKFORCE_CATEGORIES))],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employeeId])],
            'date_joined' => ['nullable', 'date'],
            'technical_background' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employeeId)],
        ];
    }
}
