<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Normalise so "abc-123" and "ABC-123" can never become two different students.
        $this->merge([
            'student_no' => strtoupper(trim((string) $this->input('student_no'))),
        ]);
    }

    public function rules(): array
    {
        /** @var Student|null $student */
        $student = $this->route('student');

        return [
            // unique() is evaluated against ALL rows, including archived ones.
            'student_no' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('students', 'student_no')->ignore($student?->id)],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('course_id', $this->input('course_id'))],
            'year_level' => ['required', 'integer', 'between:1,6'],
            'status' => ['required', Rule::in(Student::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'student_no.unique' => 'A student with this Student ID already exists (it may be archived).',
            'student_no.regex' => 'Student ID may only contain letters, numbers and dashes.',
            'section_id.exists' => 'The selected section does not belong to the selected course.',
        ];
    }
}
