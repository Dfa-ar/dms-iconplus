<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE pa_orders MODIFY current_status ENUM(
            'UNASSIGNED',
            'ASSIGNED',
            'ON_PROGRESS',
            'DONE',
            'KENDALA',
            'CLOSE_ICRM',
            'PENDING_QC',
            'PASSED',
            'REJECTED',
            'BAST_ISSUED',
            'PAID'
        ) NOT NULL DEFAULT 'UNASSIGNED'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE pa_orders MODIFY current_status ENUM(
            'UNASSIGNED',
            'ASSIGNED',
            'ON_PROGRESS',
            'DONE',
            'KENDALA'
        ) NOT NULL DEFAULT 'UNASSIGNED'");
    }
};
