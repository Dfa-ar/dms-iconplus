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
            ['kode' => 'DUMMY-BDG', 'nama' => 'Dummy KP Bandung', 'groups' => ['KP Bandung'], 'lat' => -6.9175, 'lng' => 107.6191],
            ['kode' => 'DUMMY-CRB', 'nama' => 'Dummy KP Cirebon', 'groups' => ['KP Cirebon'], 'lat' => -6.7063, 'lng' => 108.5570],
            ['kode' => 'DUMMY-TSM', 'nama' => 'Dummy KP Tasikmalaya', 'groups' => ['KP Tasikmalaya'], 'lat' => -7.3274, 'lng' => 108.2207],
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