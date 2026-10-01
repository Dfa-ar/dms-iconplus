<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->string('id_pln')->nullable()->after('customer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropIndex(['id_pln']);
            $table->dropColumn('id_pln');
        });
    }
};
