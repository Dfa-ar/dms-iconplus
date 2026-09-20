<?php

namespace Database\Seeders;

use App\Models\KendalaReason;
use Illuminate\Database\Seeder;

class KendalaReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'Pelanggan tidak bisa dihubungi',
            'Pelanggan tidak berada di lokasi',
            'Alamat tidak ditemukan',
            'Pelanggan menolak',
            'Perangkat tidak tersedia',
            'Perangkat rusak',
            'Perangkat sudah dikembalikan',
            'Kendala teknis',
            'Lainnya',
        ];

        foreach ($reasons as $label) {
            KendalaReason::firstOrCreate(['label' => $label]);
        }
    }
}
