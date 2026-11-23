<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'year_level' => fake()->numberBetween(1, 4),
            'name' => strtoupper(fake()->unique()->lexify('?')).fake()->numberBetween(1, 9),
        ];
    }
}
