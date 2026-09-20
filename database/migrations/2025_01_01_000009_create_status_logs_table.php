<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pa_id')->constrained('pa_orders')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kendala_reason_id')->nullable()->constrained('kendala_reasons')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['pa_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
    }
};
