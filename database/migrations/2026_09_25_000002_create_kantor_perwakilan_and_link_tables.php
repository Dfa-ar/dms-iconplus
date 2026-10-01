<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantor_perwakilan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->string('pic_nama')->nullable();
            $table->string('pic_unit')->nullable();
            $table->timestamps();

            $table->index('nama');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->foreignId('kantor_perwakilan_id')->nullable()->after('parent_group')->constrained('kantor_perwakilan')->nullOnDelete();
            $table->index('kantor_perwakilan_id');
        });

        Schema::table('officers', function (Blueprint $table) {
            $table->foreignId('kantor_perwakilan_id')->nullable()->after('region_id')->constrained('kantor_perwakilan')->nullOnDelete();
            $table->index('kantor_perwakilan_id');
        });

        Schema::table('pa_orders', function (Blueprint $table) {
            $table->foreignId('kantor_perwakilan_id')->nullable()->after('region_id')->constrained('kantor_perwakilan')->nullOnDelete();
            $table->index('kantor_perwakilan_id');
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropForeign(['kantor_perwakilan_id']);
            $table->dropIndex(['kantor_perwakilan_id']);
            $table->dropColumn('kantor_perwakilan_id');
        });

        Schema::table('officers', function (Blueprint $table) {
            $table->dropForeign(['kantor_perwakilan_id']);
            $table->dropIndex(['kantor_perwakilan_id']);
            $table->dropColumn('kantor_perwakilan_id');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropForeign(['kantor_perwakilan_id']);
            $table->dropIndex(['kantor_perwakilan_id']);
            $table->dropColumn('kantor_perwakilan_id');
        });

        Schema::dropIfExists('kantor_perwakilan');
    }
};
