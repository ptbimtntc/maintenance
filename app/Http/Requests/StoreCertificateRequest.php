<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\Certificate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageCertificates->value);
    }

    public function rules(): array
    {
        return [
            'certificate_type_id' => ['nullable', 'exists:certificate_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issuing_organization' => ['nullable', 'string', 'max:255'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'authorizer_name' => ['nullable', 'string', 'max:255'],
            'authorizer_title' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'related_skill_id' => ['nullable', 'exists:skills,id'],
            'related_training_program_id' => ['nullable', 'exists:training_programs,id'],
            'verification_status' => ['required', Rule::in(Certificate::VERIFICATION_STATUSES)],
            // 5 MB max; only the document formats a real certificate is issued in.
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
