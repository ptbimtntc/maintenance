<?php

namespace App\Http\Requests;

use App\Models\OvertimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOvertimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'remarks' => ['required', 'string', 'max:2000'],
            'compensation_type' => ['required', Rule::in(OvertimeEntry::COMPENSATION_TYPES)],
        ];
    }
}
