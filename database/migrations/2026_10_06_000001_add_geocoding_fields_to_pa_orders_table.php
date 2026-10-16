<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            $table->string('geocode_status', 16)->default('PENDING')->index()->after('longitude');
            $table->text('geocode_error')->nullable()->after('geocode_status');
        });

        DB::table('pa_orders')
            ->where(function ($query) {
                $query->whereNull('address')->orWhere('address', '');
            })
            ->update([
                'geocode_status' => 'FAILED',
                'geocode_error' => 'Alamat pelanggan belum diisi.',
            ]);
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropIndex(['geocode_status']);
            $table->dropColumn(['latitude', 'longitude', 'geocode_status', 'geocode_error']);
        });
    }
};