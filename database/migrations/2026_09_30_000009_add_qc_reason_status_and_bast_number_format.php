<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qc_reject_reasons', 'is_active')) {
            Schema::table('qc_reject_reasons', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('description');
            });
        }

        if (! Schema::hasColumn('sla_settings', 'bast_number_format')) {
            Schema::table('sla_settings', function (Blueprint $table) {
                $table->string('bast_number_format', 100)
                    ->default('{sequence}/BAST/{kp_code}/{year}')
                    ->after('aging_orange_max');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sla_settings', 'bast_number_format')) {
            Schema::table('sla_settings', function (Blueprint $table) {
                $table->dropColumn('bast_number_format');
            });
        }

        if (Schema::hasColumn('qc_reject_reasons', 'is_active')) {
            Schema::table('qc_reject_reasons', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};