<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\JobDescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageJobDescriptions->value);
    }

    public function rules(): array
    {
        return [
            'position_id' => ['required', 'exists:positions,id'],
            'reports_to_position_id' => ['nullable', 'exists:positions,id'],
            'job_title' => ['required', 'string', 'max:255'],
            'job_purpose' => ['nullable', 'string', 'max:2000'],
            'main_responsibilities' => ['nullable', 'string', 'max:4000'],
            'detailed_duties' => ['nullable', 'string', 'max:4000'],
            'required_education' => ['nullable', 'string', 'max:255'],
            'required_experience' => ['nullable', 'string', 'max:255'],
            'required_technical_skills' => ['nullable', 'string', 'max:2000'],
            'required_soft_skills' => ['nullable', 'string', 'max:2000'],
            'required_certifications' => ['nullable', 'string', 'max:2000'],
            'safety_responsibilities' => ['nullable', 'string', 'max:2000'],
            'direct_reports_summary' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'status' => ['required', Rule::in(JobDescription::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
