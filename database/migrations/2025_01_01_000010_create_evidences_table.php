<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pa_id')->constrained('pa_orders')->cascadeOnDelete();
            $table->enum('type', [
                'perangkat',
                'modem_ont',
                'serah_terima',
                'kendala',
                'k3_awal',
                'ont_depan',
                'ont_belakang_sn',
                'fat_terdekat',
                'kwh_meter',
                'k3_akhir',
                'ba_pengambilan_perangkat',
            ]);
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->string('receiver_name')->nullable();
            $table->timestamp('pickup_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
