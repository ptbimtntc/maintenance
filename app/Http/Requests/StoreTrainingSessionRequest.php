<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\TrainingSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageTraining->value);
    }

    public function rules(): array
    {
        return [
            'session_title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(TrainingSession::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
