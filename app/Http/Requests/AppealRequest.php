<?php

namespace App\Http\Requests;

use App\Models\Appeal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * store  -> admin logs an appeal on behalf of a student
 * decide -> admin upholds or overturns it
 */
class AppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        if ($this->route()?->getActionMethod() === 'decide') {
            return [
                'decision' => ['required', Rule::in([Appeal::UPHELD, Appeal::OVERTURNED])],
                'remarks' => ['required', 'string', 'max:2000'],
            ];
        }

        return [
            'statement' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
