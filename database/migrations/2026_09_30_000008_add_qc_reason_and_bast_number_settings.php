<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_reject_reasons', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::table('sla_settings', function (Blueprint $table) {
            $table->string('bast_number_format', 100)
                ->default('{sequence}/BAST/{kp_code}/{year}')
                ->after('aging_orange_max');
        });
    }

    public function down(): void
    {
        Schema::table('sla_settings', function (Blueprint $table) {
            $table->dropColumn('bast_number_format');
        });

        Schema::table('qc_reject_reasons', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};