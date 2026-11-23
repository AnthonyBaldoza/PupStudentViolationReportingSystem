<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplinaryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('disciplinary_actions', 'name')->ignore($this->route('disciplinaryAction')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
