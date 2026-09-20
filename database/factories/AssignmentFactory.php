<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'pa_id' => PaOrder::inRandomOrder()->value('id'),
            'officer_id' => Officer::inRandomOrder()->value('id'),
            'assign_date' => $this->faker->dateTimeBetween('-14 days', 'now'),
            'assigned_by' => User::inRandomOrder()->value('id'),
            'source' => $this->faker->randomElement(['auto', 'manual']),
            'released_at' => null,
        ];
    }
}
