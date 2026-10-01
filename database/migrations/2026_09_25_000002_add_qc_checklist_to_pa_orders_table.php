<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->json('qc_checklist')->nullable()->after('qc_note');
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropColumn('qc_checklist');
        });
    }
};
