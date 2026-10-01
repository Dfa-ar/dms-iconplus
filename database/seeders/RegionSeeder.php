<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /** Seed all regencies and cities in West Java without overwriting existing links. */
    public function run(): void
    {
        $regions = [
            'Bandung Raya' => [
                'Kota Bandung',
                'Kab. Bandung',
                'Kab. Bandung Barat',
                'Kota Cimahi',
                'Kab. Sumedang',
            ],
            'Bogor Raya' => [
                'Kota Bogor',
                'Kab. Bogor',
                'Kota Depok',
            ],
            'Bekasi Raya' => [
                'Kota Bekasi',
                'Kab. Bekasi',
            ],
            'Purwasuka' => [
                'Kab. Purwakarta',
                'Kab. Karawang',
                'Kab. Subang',
            ],
            'Cirebon' => [
                'Kota Cirebon',
                'Kab. Cirebon',
                'Kab. Indramayu',
                'Kab. Kuningan',
                'Kab. Majalengka',
            ],
            'Tasikmalaya' => [
                'Kota Tasikmalaya',
                'Kab. Tasikmalaya',
                'Kab. Ciamis',
                'Kab. Garut',
                'Kab. Pangandaran',
                'Kota Banjar',
            ],
            'Sukabumi' => [
                'Kab. Sukabumi',
                'Kota Sukabumi',
                'Kab. Cianjur',
            ],
        ];
        $coordinates = [
            'Kota Bandung' => [-6.9175, 107.6191],
            'Kab. Bandung' => [-7.0244, 107.5186],
            'Kab. Bandung Barat' => [-6.8396, 107.4791],
            'Kota Cimahi' => [-6.8722, 107.5425],
            'Kab. Sumedang' => [-6.8586, 107.9166],
            'Kota Bogor' => [-6.5950, 106.8167],
            'Kab. Bogor' => [-6.4850, 106.8425],
            'Kota Depok' => [-6.4025, 106.7942],
            'Kota Bekasi' => [-6.2383, 106.9756],
            'Kab. Bekasi' => [-6.2610, 107.1520],
            'Kab. Purwakarta' => [-6.5569, 107.4422],
            'Kab. Karawang' => [-6.3057, 107.3136],
            'Kab. Subang' => [-6.5715, 107.7587],
            'Kota Cirebon' => [-6.7063, 108.5570],
            'Kab. Cirebon' => [-6.7606, 108.4836],
            'Kab. Indramayu' => [-6.3267, 108.3200],
            'Kab. Kuningan' => [-6.9760, 108.4830],
            'Kab. Majalengka' => [-6.8360, 108.2270],
            'Kota Tasikmalaya' => [-7.3274, 108.2207],
            'Kab. Tasikmalaya' => [-7.3510, 108.1115],
            'Kab. Ciamis' => [-7.3257, 108.3534],
            'Kab. Garut' => [-7.2167, 107.9000],
            'Kab. Pangandaran' => [-7.6840, 108.6500],
            'Kota Banjar' => [-7.3690, 108.5340],
            'Kab. Sukabumi' => [-6.9875, 106.5513],
            'Kota Sukabumi' => [-6.9277, 106.9299],
            'Kab. Cianjur' => [-6.8173, 107.1403],
        ];

        foreach ($regions as $parentGroup => $kabupatenKotaList) {
            foreach ($kabupatenKotaList as $kabupatenKota) {
                $region = Region::firstOrCreate(
                    ['kabupaten_kota' => $kabupatenKota],
                    ['parent_group' => $parentGroup]
                );

                if ($region->lat === null && $region->lng === null && isset($coordinates[$kabupatenKota])) {
                    $region->update([
                        'lat' => $coordinates[$kabupatenKota][0],
                        'lng' => $coordinates[$kabupatenKota][1],
                    ]);
                }
            }
        }
    }
}
