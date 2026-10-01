<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('office_code')->nullable()->after('kabupaten_kota');
            $table->string('office_name')->nullable()->after('office_code');
            $table->string('level')->nullable()->after('office_name');
            $table->foreignId('parent_region_id')->nullable()->after('level')->constrained('regions')->nullOnDelete();
            $table->boolean('is_office')->default(false)->after('parent_region_id');

            $table->index(['is_office', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropForeign(['parent_region_id']);
            $table->dropIndex(['is_office', 'level']);
            $table->dropColumn(['office_code', 'office_name', 'level', 'parent_region_id', 'is_office']);
        });
    }
};
