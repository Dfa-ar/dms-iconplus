<?php

namespace Database\Factories;

use App\Models\KendalaReason;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\UploadBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaOrder>
 */
class PaOrderFactory extends Factory
{
    protected $model = PaOrder::class;

    public function definition(): array
    {
        $paDate = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'pa_number' => 'PA-'.strtoupper($this->faker->bothify('???-####-????')),
            'customer_id' => 'ICON-'.$this->faker->unique()->numerify('######'),
            'customer_name' => $this->faker->name(),
            'contact_phone' => '08'.$this->faker->numerify('##########'),
            'address' => $this->faker->streetAddress(),
            'region_id' => Region::inRandomOrder()->value('id'),
            'pa_date' => $paDate,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
            'assigned_date' => null,
            'started_at' => null,
            'completed_at' => null,
            'kendala_reason_id' => null,
            'notes' => null,
            'batch_id' => UploadBatch::inRandomOrder()->value('id'),
        ];
    }

    // Sudah ditugaskan ke petugas, belum dikerjakan
    public function assigned(): static
    {
        return $this->state(function (array $attrs) {
            $assignedDate = $this->faker->dateTimeBetween($attrs['pa_date'], 'now');

            return [
                'current_status' => PaOrder::STATUS_ASSIGNED,
                'current_officer_id' => Officer::inRandomOrder()->value('id'),
                'assigned_date' => $assignedDate,
            ];
        });
    }

    // Sedang dikerjakan petugas
    public function onProgress(): static
    {
        return $this->assigned()->state(function (array $attrs) {
            return [
                'current_status' => PaOrder::STATUS_ON_PROGRESS,
                'started_at' => $this->faker->dateTimeBetween($attrs['assigned_date'], 'now'),
            ];
        });
    }

    // Selesai dikerjakan
    public function done(): static
    {
        return $this->onProgress()->state(function (array $attrs) {
            return [
                'current_status' => PaOrder::STATUS_DONE,
                'completed_at' => $this->faker->dateTimeBetween($attrs['started_at'], 'now'),
            ];
        });
    }

    // Terkendala di lapangan
    public function kendala(): static
    {
        return $this->onProgress()->state(function (array $attrs) {
            return [
                'current_status' => PaOrder::STATUS_KENDALA,
                'kendala_reason_id' => KendalaReason::inRandomOrder()->value('id'),
                'notes' => $this->faker->sentence(),
            ];
        });
    }

    // PA lama yang sengaja dibuat "terlambat" untuk uji fitur aging/Over SLA
    public function overdue(int $daysAgo = 20): static
    {
        return $this->state(fn () => [
            'pa_date' => now()->subDays($daysAgo),
        ]);
    }
}
