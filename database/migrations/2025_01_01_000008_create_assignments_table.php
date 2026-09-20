<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pa_id')->constrained('pa_orders')->cascadeOnDelete();
            $table->foreignId('officer_id')->constrained('officers')->cascadeOnDelete();
            $table->date('assign_date');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['auto', 'manual'])->default('auto');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['officer_id', 'assign_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
