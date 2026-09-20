<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\PaOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['perangkat', 'modem_ont', 'serah_terima']);

        return [
            'pa_id' => PaOrder::inRandomOrder()->value('id'),
            'type' => $type,
            // placeholder path — di aplikasi asli ini hasil upload ke storage/S3
            'file_path' => 'evidences/'.$this->faker->uuid().'.jpg',
            'uploaded_by' => User::inRandomOrder()->value('id'),
            'uploaded_at' => now(),
            'receiver_name' => $type === 'serah_terima' ? $this->faker->name() : null,
            'pickup_time' => now(),
        ];
    }
}
