<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast_documents', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_bast')->unique();
            $table->foreignId('region_id')->constrained('regions');
            $table->foreignId('created_by')->constrained('users');
            $table->date('tanggal');
            $table->string('status')->default('DRAFT');
            $table->text('notes')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast_documents');
    }
};
