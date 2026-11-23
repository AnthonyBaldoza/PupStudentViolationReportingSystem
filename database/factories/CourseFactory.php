<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $code = 'DEMO-'.strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => 'Demo Program '.$code,
            'is_active' => true,
        ];
    }
}
