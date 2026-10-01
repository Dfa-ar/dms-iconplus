<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantor_perwakilan', function (Blueprint $table) {
            $table->string('kode', 30)->nullable()->unique()->after('nama');
        });

        Schema::table('bast_documents', function (Blueprint $table) {
            $table->foreignId('kantor_perwakilan_id')->nullable()->after('region_id')->constrained('kantor_perwakilan')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
        });

        Schema::create('bast_item_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_item_id')->unique();
            $table->foreignId('bast_document_id')->constrained('bast_documents')->cascadeOnDelete();
            $table->foreignId('pa_id')->constrained('pa_orders');
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->string('archive_reason');
            $table->timestamp('archived_at');
            $table->timestamps();
        });

        $duplicatePaIds = DB::table('bast_items')
            ->select('pa_id')
            ->groupBy('pa_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pa_id');

        foreach ($duplicatePaIds as $paId) {
            $items = DB::table('bast_items')
                ->where('pa_id', $paId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($items->skip(1) as $item) {
                DB::table('bast_item_archives')->insert([
                    'original_item_id' => $item->id,
                    'bast_document_id' => $item->bast_document_id,
                    'pa_id' => $item->pa_id,
                    'serial_number' => $item->serial_number,
                    'location' => $item->location,
                    'archive_reason' => 'Arsip item duplikat sebelum constraint PA unik; item pertama dipertahankan.',
                    'archived_at' => now(),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);

                DB::table('bast_items')->where('id', $item->id)->delete();
            }
        }

        Schema::table('bast_items', function (Blueprint $table) {
            $table->unique('pa_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pa_orders MODIFY current_status ENUM(
                'UNASSIGNED', 'ASSIGNED', 'ON_PROGRESS', 'DONE', 'KENDALA', 'CLOSE_ICRM',
                'PENDING_QC', 'PASSED', 'REJECTED', 'BAST_ISSUED', 'BAST_VOID', 'PAID'
            ) NOT NULL DEFAULT 'UNASSIGNED'");
        }
    }

    public function down(): void
    {
        Schema::table('bast_items', function (Blueprint $table) {
            $table->dropUnique(['pa_id']);
        });

        foreach (DB::table('bast_item_archives')->orderBy('original_item_id')->get() as $item) {
            DB::table('bast_items')->insert([
                'id' => $item->original_item_id,
                'bast_document_id' => $item->bast_document_id,
                'pa_id' => $item->pa_id,
                'serial_number' => $item->serial_number,
                'location' => $item->location,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);
        }

        Schema::dropIfExists('bast_item_archives');

        Schema::table('bast_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kantor_perwakilan_id');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['void_reason', 'voided_at']);
        });

        Schema::table('kantor_perwakilan', function (Blueprint $table) {
            $table->dropUnique(['kode']);
            $table->dropColumn('kode');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pa_orders MODIFY current_status ENUM(
                'UNASSIGNED', 'ASSIGNED', 'ON_PROGRESS', 'DONE', 'KENDALA', 'CLOSE_ICRM',
                'PENDING_QC', 'PASSED', 'REJECTED', 'BAST_ISSUED', 'PAID'
            ) NOT NULL DEFAULT 'UNASSIGNED'");
        }
    }
};