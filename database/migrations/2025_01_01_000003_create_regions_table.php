<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('kabupaten_kota');
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('parent_group')->nullable(); // mis. Bandung, Cirebon, Tasikmalaya
            $table->timestamps();

            $table->index('kabupaten_kota');
            $table->index('parent_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
