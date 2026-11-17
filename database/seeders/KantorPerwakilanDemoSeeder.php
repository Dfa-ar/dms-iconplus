<?php

namespace Database\Seeders;

use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KantorPerwakilanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            ['kode' => 'BDG', 'nama' => 'KP Bandung', 'groups' => ['KP Bandung'], 'lat' => -6.9175, 'lng' => 107.6191],
            ['kode' => 'CRB', 'nama' => 'KP Cirebon', 'groups' => ['KP Cirebon'], 'lat' => -6.7063, 'lng' => 108.5570],
            ['kode' => 'TSM', 'nama' => 'KP Tasikmalaya', 'groups' => ['KP Tasikmalaya'], 'lat' => -7.3274, 'lng' => 108.2207],
        ];

        $legacyOffices = [
            'DUMMY-BDG' => 'BDG',
            'DUMMY-BGR' => 'BDG',
            'DUMMY-BKS' => 'TSM',
            'DUMMY-PWK' => 'BDG',
            'DUMMY-CRB' => 'CRB',
            'DUMMY-TSM' => 'TSM',
            'DUMMY-SKB' => 'BDG',
        ];

        DB::transaction(function () use ($offices, $legacyOffices) {
            $officesByCode = [];

            foreach ($offices as $data) {
                $officesByCode[$data['kode']] = KantorPerwakilan::updateOrCreate(
                    ['kode' => $data['kode']],
                    [
                        'nama' => $data['nama'],
                        'latitude' => $data['lat'],
                        'longitude' => $data['lng'],
                        'alamat' => null,
                        'pic_nama' => null,
                        'pic_unit' => null,
                    ]
                );
            }

            foreach ($legacyOffices as $legacyCode => $targetCode) {
                $legacyOffice = KantorPerwakilan::where('kode', $legacyCode)->first();

                if (! $legacyOffice) {
                    continue;
                }

                $targetOfficeId = $officesByCode[$targetCode]->id;

                foreach (['regions', 'officers', 'pa_orders', 'fat_points', 'bast_documents'] as $table) {
                    DB::table($table)
                        ->where('kantor_perwakilan_id', $legacyOffice->id)
                        ->update(['kantor_perwakilan_id' => $targetOfficeId]);
                }

                $legacyOffice->delete();
            }

            foreach ($offices as $data) {
                $office = $officesByCode[$data['kode']];

                Region::query()
                    ->whereIn('parent_group', $data['groups'])
                    ->update(['kantor_perwakilan_id' => $office->id]);

                $regionIds = Region::query()
                    ->whereIn('parent_group', $data['groups'])
                    ->pluck('id');

                PaOrder::query()
                    ->whereIn('region_id', $regionIds)
                    ->update(['kantor_perwakilan_id' => $office->id]);

                Officer::query()
                    ->whereIn('region_id', $regionIds)
                    ->update(['kantor_perwakilan_id' => $office->id]);

                foreach (['fat_points', 'bast_documents'] as $table) {
                    DB::table($table)
                        ->whereIn('region_id', $regionIds)
                        ->update(['kantor_perwakilan_id' => $office->id]);
                }
            }
        });
    }
}
