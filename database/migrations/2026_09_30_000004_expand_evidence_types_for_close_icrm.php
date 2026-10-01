<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $types = [
        'perangkat',
        'modem_ont',
        'serah_terima',
        'kendala',
        'k3_awal',
        'ont_depan',
        'ont_belakang_sn',
        'fat_terdekat',
        'kwh_meter',
        'k3_akhir',
        'ba_pengambilan_perangkat',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $types = implode("','", $this->types);
        DB::statement("ALTER TABLE evidences MODIFY type ENUM('{$types}') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE evidences MODIFY type ENUM('perangkat','modem_ont','serah_terima','kendala') NOT NULL");
    }
};