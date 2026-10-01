<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->foreignId('fat_point_id')->nullable()->after('region_id')->constrained('fat_points')->nullOnDelete();
            $table->foreignId('splitter_id')->nullable()->after('fat_point_id')->constrained('splitters')->nullOnDelete();
            $table->unsignedInteger('port_number')->nullable()->after('splitter_id');
            $table->boolean('sn_ont_readable')->nullable()->after('port_number');
            $table->string('kwh_status')->nullable()->after('sn_ont_readable');
            $table->string('close_icrm_step')->nullable()->after('kwh_status');
            $table->unique(['splitter_id', 'port_number'], 'pa_orders_splitter_port_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropUnique('pa_orders_splitter_port_unique');
            $table->dropConstrainedForeignId('fat_point_id');
            $table->dropConstrainedForeignId('splitter_id');
            $table->dropColumn(['port_number', 'sn_ont_readable', 'kwh_status', 'close_icrm_step']);
        });
    }
};
