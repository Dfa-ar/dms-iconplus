<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->date('payment_date');
            $table->string('method', 30);
            $table->string('reference_no', 120)->nullable();
            $table->string('proof_path');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['payment_date', 'method']);
        });

        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_batch_id')->constrained('payment_batches')->cascadeOnDelete();
            $table->foreignId('pa_id')->constrained('pa_orders');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payment_batch_id', 'pa_id']);
            $table->index('pa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_items');
        Schema::dropIfExists('payment_batches');
    }
};