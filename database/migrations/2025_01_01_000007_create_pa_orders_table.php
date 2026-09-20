<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pa_orders', function (Blueprint $table) {
            $table->id();
            $table->string('pa_number')->unique();
            $table->string('customer_id')->nullable();
            $table->string('customer_name');
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->date('pa_date');
            $table->enum('current_status', [
                'UNASSIGNED', 'ASSIGNED', 'ON_PROGRESS', 'DONE', 'KENDALA',
            ])->default('UNASSIGNED');
            $table->foreignId('current_officer_id')->nullable()->constrained('officers')->nullOnDelete();
            $table->date('assigned_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('kendala_reason_id')->nullable()->constrained('kendala_reasons')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('upload_batches')->nullOnDelete();
            $table->timestamps();

            $table->index('current_status');
            $table->index(['region_id', 'current_status']);
            $table->index(['current_officer_id', 'assigned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_orders');
    }
};
