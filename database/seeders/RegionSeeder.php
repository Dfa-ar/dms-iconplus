<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Data wilayah nyata (Jawa Barat) mengikuti pengelompokan yang sama
     * seperti di mockup Control Tower ("Progress Per Wilayah"): Bandung Raya,
     * Cirebon, Purwakarta, Tasikmalaya, Sukabumi.
     */
    public function run(): void
    {
        $regions = [
            'Bandung Raya' => [
                'Kota Bandung',
                'Kab. Bandung',
                'Kab. Bandung Barat',
                'Kota Cimahi',
            ],
            'Cirebon' => [
                'Kota Cirebon',
                'Kab. Cirebon',
            ],
            'Purwakarta' => [
                'Kab. Purwakarta',
            ],
            'Tasikmalaya' => [
                'Kota Tasikmalaya',
                'Kab. Tasikmalaya',
            ],
            'Sukabumi' => [
                'Kab. Sukabumi',
                'Kota Sukabumi',
            ],
        ];

        foreach ($regions as $parentGroup => $kabupatenKotaList) {
            foreach ($kabupatenKotaList as $kabupatenKota) {
                Region::firstOrCreate([
                    'kabupaten_kota' => $kabupatenKota,
                    'parent_group' => $parentGroup,
                ]);
            }
        }
    }
}
