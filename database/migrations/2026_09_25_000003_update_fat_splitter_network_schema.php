<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fat_points', function (Blueprint $table) {
            $table->foreignId('kantor_perwakilan_id')->nullable()->after('region_id')->constrained('kantor_perwakilan')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('kantor_perwakilan_id');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            $table->index('kantor_perwakilan_id');
        });

        Schema::table('splitters', function (Blueprint $table) {
            $table->unsignedInteger('capacity_port')->nullable()->after('fat_point_id');
            $table->index(['fat_point_id', 'capacity_port']);
        });

        Schema::table('splitters', function (Blueprint $table) {
            if (! Schema::hasColumn('splitters', 'capacity_port')) {
                return;
            }

            $table->unsignedInteger('total_port')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('splitters', function (Blueprint $table) {
            $table->dropIndex(['fat_point_id', 'capacity_port']);
            $table->dropColumn('capacity_port');
        });

        Schema::table('fat_points', function (Blueprint $table) {
            $table->dropForeign(['kantor_perwakilan_id']);
            $table->dropIndex(['kantor_perwakilan_id']);
            $table->dropColumn(['kantor_perwakilan_id', 'latitude', 'longitude']);
        });
    }
};
