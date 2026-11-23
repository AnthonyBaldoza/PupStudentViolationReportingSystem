<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generates clearly fake students (IDs are prefixed DEMO-). No real personal data.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_no' => 'DEMO-'.fake()->unique()->numerify('######'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.7)->lastName(),
            'course_id' => Course::factory(),
            'section_id' => null,
            'year_level' => fake()->numberBetween(1, 4),
            'status' => Student::ACTIVE,
        ];
    }
}
