<?php

namespace Database\Seeders;

use App\Models\QcRejectReason;
use Illuminate\Database\Seeder;

class QcRejectReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'sn_ont_tidak_terbaca' => 'SN ONT tidak terbaca (auto-reject)',
            'foto_apd_tidak_lengkap' => 'Foto APD awal/akhir tidak lengkap',
            'data_kabel_tidak_lengkap' => 'Data kabel/FAT/splitter/port tidak lengkap',
            'id_pln_kwh_tidak_sesuai' => 'ID PLN atau KWH Meter tidak sesuai ketentuan',
            'ba_pengambilan_tidak_lengkap' => 'BA Pengambilan Perangkat tidak ada/tidak ditandatangani pelanggan',
            'lainnya' => 'Lainnya',
        ];

        foreach ($reasons as $code => $label) {
            QcRejectReason::updateOrCreate(['code' => $code], ['label' => $label]);
        }
    }
}