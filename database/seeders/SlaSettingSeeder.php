<?php

namespace Database\Seeders;

use App\Models\SlaSetting;
use Illuminate\Database\Seeder;

class SlaSettingSeeder extends Seeder
{
    public function run(): void
    {
        // [ASUMSI] nilai awal — konfirmasikan ke pembimbing lapangan sebelum dikunci
        SlaSetting::firstOrCreate([], [
            'sla_days' => 14,
            'aging_green_max' => 2,
            'aging_yellow_max' => 6,
            'aging_orange_max' => 13,
        ]);
    }
}
