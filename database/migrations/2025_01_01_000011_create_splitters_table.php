<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitters', function (Blueprint $table) {
            $table->id();
            $table->string('kode_splitter')->unique();
            $table->string('nama_splitter');
            $table->foreignId('fat_point_id')->nullable()->constrained('fat_points')->nullOnDelete();
            $table->unsignedInteger('total_port')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitters');
    }
};
