<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->boolean('id_pln_confirmed')->nullable()->after('id_pln');
            $table->decimal('kabel_panjang_meter', 8, 2)->default(0)->after('port_number');
            $table->string('kwh_note')->nullable()->after('kwh_status');
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropColumn(['id_pln_confirmed', 'kabel_panjang_meter', 'kwh_note']);
        });
    }
};