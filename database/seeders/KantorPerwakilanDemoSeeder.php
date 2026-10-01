<?php

namespace Database\Seeders;

use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use Illuminate\Database\Seeder;

class KantorPerwakilanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            ['kode' => 'DUMMY-BDG', 'nama' => 'Dummy KP Bandung Raya', 'groups' => ['Bandung Raya', 'Bandung'], 'lat' => -6.9175, 'lng' => 107.6191],
            ['kode' => 'DUMMY-BGR', 'nama' => 'Dummy KP Bogor Raya', 'groups' => ['Bogor Raya'], 'lat' => -6.5950, 'lng' => 106.8167],
            ['kode' => 'DUMMY-BKS', 'nama' => 'Dummy KP Bekasi Raya', 'groups' => ['Bekasi Raya'], 'lat' => -6.2383, 'lng' => 106.9756],
            ['kode' => 'DUMMY-PWK', 'nama' => 'Dummy KP Purwasuka', 'groups' => ['Purwasuka', 'Purwakarta'], 'lat' => -6.5569, 'lng' => 107.4422],
            ['kode' => 'DUMMY-CRB', 'nama' => 'Dummy KP Cirebon', 'groups' => ['Cirebon'], 'lat' => -6.7063, 'lng' => 108.5570],
            ['kode' => 'DUMMY-TSM', 'nama' => 'Dummy KP Tasikmalaya', 'groups' => ['Tasikmalaya'], 'lat' => -7.3274, 'lng' => 108.2207],
            ['kode' => 'DUMMY-SKB', 'nama' => 'Dummy KP Sukabumi', 'groups' => ['Sukabumi'], 'lat' => -6.9277, 'lng' => 106.9299],
        ];

        foreach ($offices as $data) {
            $office = KantorPerwakilan::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'nama' => $data['nama'],
                    'alamat' => 'Data dummy untuk pengujian.',
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'pic_nama' => 'PIC Demo',
                    'pic_unit' => 'Unit Demo',
                ]
            );

            Region::query()
                ->whereIn('parent_group', $data['groups'])
                ->whereNull('kantor_perwakilan_id')
                ->update(['kantor_perwakilan_id' => $office->id]);

            $regionIds = $office->regions()->pluck('regions.id');
            if ($regionIds->isEmpty()) {
                continue;
            }

            PaOrder::query()
                ->whereIn('region_id', $regionIds)
                ->whereNull('kantor_perwakilan_id')
                ->update(['kantor_perwakilan_id' => $office->id]);

            Officer::query()
                ->whereIn('region_id', $regionIds)
                ->whereNull('kantor_perwakilan_id')
                ->update(['kantor_perwakilan_id' => $office->id]);
        }
    }
}