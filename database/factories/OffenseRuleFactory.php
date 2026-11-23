<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\OffenseRule;
use App\Models\ViolationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffenseRule>
 */
class OffenseRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'violation_category_id' => ViolationCategory::factory(),
            'offense_number' => 1,
            'is_succeeding' => false,
            'description' => 'Placeholder rule description.',
            'disciplinary_action_id' => DisciplinaryAction::factory(),
            'is_active' => true,
        ];
    }

    public function succeeding(): static
    {
        return $this->state(fn () => ['is_succeeding' => true]);
    }
}
