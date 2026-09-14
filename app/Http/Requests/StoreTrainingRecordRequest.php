<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\TrainingRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageTrainingRecords->value);
    }

    public function rules(): array
    {
        return [
            'training_program_id' => ['nullable', 'exists:training_programs,id'],
            'training_session_id' => ['nullable', 'exists:training_sessions,id'],
            'training_date' => ['required', 'date'],
            'training_type_id' => ['nullable', 'exists:training_types,id'],
            'training_provider_id' => ['nullable', 'exists:training_providers,id'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'duration_hours' => ['nullable', 'numeric', 'min:0'],
            'attendance_status' => ['required', Rule::in(TrainingRecord::ATTENDANCE_STATUSES)],
            'completion_status' => ['required', Rule::in(TrainingRecord::COMPLETION_STATUSES)],
            'assessment_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assessment_result' => ['nullable', Rule::in(['pass', 'fail'])],
            'competency_before_level_id' => ['nullable', 'exists:competency_levels,id'],
            'competency_after_level_id' => ['nullable', 'exists:competency_levels,id'],
            'certificate_issued' => ['sometimes', 'boolean'],
            'certificate_reference' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
