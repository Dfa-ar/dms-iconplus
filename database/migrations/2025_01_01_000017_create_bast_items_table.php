<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bast_document_id')->constrained('bast_documents')->cascadeOnDelete();
            $table->foreignId('pa_id')->constrained('pa_orders');
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast_items');
    }
};
