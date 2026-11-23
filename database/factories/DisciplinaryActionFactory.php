<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplinaryAction>
 */
class DisciplinaryActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '[DEMO] Action '.fake()->unique()->bothify('??-###'),
            'description' => 'Placeholder action. Replace with the official handbook penalty.',
            'is_active' => true,
        ];
    }
}
