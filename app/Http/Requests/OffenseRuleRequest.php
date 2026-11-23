<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffenseRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $rule = $this->route('rule');                                  // set when editing
        $category = $this->route('category') ?? $rule?->category;     // set when adding

        return [
            'offense_number' => [
                'required', 'integer', 'between:1,99',
                Rule::unique('offense_rules', 'offense_number')
                    ->where('violation_category_id', $category?->id)
                    ->ignore($rule?->id),
            ],
            'is_succeeding' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Only active actions can be chosen (the rule's current action stays valid when editing).
            'disciplinary_action_id' => [
                'required', 'integer',
                Rule::exists('disciplinary_actions', 'id')->where(function ($query) use ($rule) {
                    $query->where(function ($q) use ($rule) {
                        $q->where('is_active', true);
                        if ($rule) {
                            $q->orWhere('id', $rule->disciplinary_action_id);
                        }
                    });
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'offense_number.unique' => 'This category already has a rule for that offense number.',
        ];
    }
}
