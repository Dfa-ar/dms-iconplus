<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('qc_reject_reasons')
            ->where('code', 'sn_ont_tidak_terbaca')
            ->update(['label' => 'SN ONT tidak terbaca']);
    }

    public function down(): void
    {
        DB::table('qc_reject_reasons')
            ->where('code', 'sn_ont_tidak_terbaca')
            ->update(['label' => 'SN ONT tidak terbaca (auto-reject)']);
    }
};