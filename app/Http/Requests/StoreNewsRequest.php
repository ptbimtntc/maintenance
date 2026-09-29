<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo(PermissionName::ManageNews->value);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'template' => ['required', Rule::in(News::TEMPLATES)],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_published' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
