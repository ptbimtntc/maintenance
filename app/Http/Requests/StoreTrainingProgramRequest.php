<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\TrainingProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageTraining->value);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('training_programs', 'code')->ignore($this->route('training_program'))],
            'training_category_id' => ['nullable', 'exists:training_categories,id'],
            'training_type_id' => ['nullable', 'exists:training_types,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'objectives' => ['nullable', 'string', 'max:2000'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'is_internal' => ['sometimes', 'boolean'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'training_provider_id' => ['nullable', 'exists:training_providers,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'duration_value' => ['nullable', 'numeric', 'min:0'],
            'duration_unit' => ['nullable', Rule::in(['hours', 'days'])],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'budget_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(TrainingProgram::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['exists:skills,id'],
        ];
    }
}
