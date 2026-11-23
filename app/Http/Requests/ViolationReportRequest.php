<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for submitting a new report and for resubmitting a returned one.
 */
class ViolationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOfficer() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'violation_category_id' => ['required', 'integer', Rule::exists('violation_categories', 'id')->where('is_active', true)],
            'incident_date' => ['required', 'date', 'after:2000-01-01', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            // Restrict by extension AND detected MIME type; 5 MB each.
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ];

        // When editing a returned report the student cannot be changed.
        if ($this->route('record')) {
            unset($rules['student_id']);
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'violation_category_id' => 'violation category',
            'student_id' => 'student',
            'evidence.*' => 'evidence file',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Please search for and select a student.',
            'incident_date.before_or_equal' => 'The incident date cannot be in the future.',
            'evidence.*.mimes' => 'Evidence must be a JPG, PNG or PDF file.',
            'evidence.*.max' => 'Each evidence file may not be larger than 5 MB.',
        ];
    }
}
