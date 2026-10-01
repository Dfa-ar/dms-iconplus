<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->string('qc_status')->nullable()->after('batch_id');
            $table->unsignedBigInteger('qc_reject_reason_id')->nullable()->after('qc_status');
            $table->text('qc_note')->nullable()->after('qc_reject_reason_id');
            $table->unsignedBigInteger('bast_document_id')->nullable()->after('qc_note');
            $table->string('payment_status')->nullable()->after('bast_document_id');
            $table->string('serial_number_ont')->nullable()->after('payment_status');
            $table->string('kondisi_ont')->nullable()->after('serial_number_ont');
            $table->string('adaptor')->nullable()->after('kondisi_ont');
        });
    }

    public function down(): void
    {
        Schema::table('pa_orders', function (Blueprint $table) {
            $table->dropColumn(['qc_status', 'qc_reject_reason_id', 'qc_note', 'bast_document_id', 'payment_status', 'serial_number_ont', 'kondisi_ont', 'adaptor']);
        });
    }
};
