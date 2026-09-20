<?php

namespace Database\Factories;

use App\Models\PaOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StatusLog>
 */
class StatusLogFactory extends Factory
{
    protected $model = \App\Models\StatusLog::class;

    public function definition(): array
    {
        return [
            'pa_id' => PaOrder::inRandomOrder()->value('id'),
            'from_status' => PaOrder::STATUS_UNASSIGNED,
            'to_status' => PaOrder::STATUS_ASSIGNED,
            'changed_by' => User::inRandomOrder()->value('id'),
            'kendala_reason_id' => null,
            'note' => null,
            'changed_at' => now(),
        ];
    }
}
