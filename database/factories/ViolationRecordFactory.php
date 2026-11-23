<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates PENDING records only. Use ViolationWorkflowService::approve() to get real
 * offense numbers; the factory never fabricates offense numbering.
 *
 * @extends Factory<ViolationRecord>
 */
class ViolationRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'violation_category_id' => ViolationCategory::factory(),
            'category_name' => 'Category',
            'reported_by' => User::factory(),
            'incident_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'incident_time' => '09:30:00',
            'location' => fake()->randomElement(['Main Gate', 'Library', 'Canteen', 'Room 101']),
            'description' => fake()->sentence(12),
            'status' => ViolationRecord::PENDING,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ViolationRecord $record) {
            $record->category_name = ViolationCategory::find($record->violation_category_id)?->name ?? 'Category';
        });
    }
}
