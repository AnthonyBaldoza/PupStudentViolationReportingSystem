<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve (remarks optional), reject / return for information (remarks required).
 */
class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $isApprove = $this->route()?->getActionMethod() === 'approve';

        return [
            'remarks' => [$isApprove ? 'nullable' : 'required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['remarks.required' => 'Please explain your decision so the officer knows what to do next.'];
    }
}
