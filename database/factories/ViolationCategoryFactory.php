<?php

namespace Database\Factories;

use App\Models\ViolationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViolationCategory>
 */
class ViolationCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '[DEMO] Category '.fake()->unique()->bothify('??-###'),
            'description' => 'Placeholder category for testing. Not an official school violation.',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
