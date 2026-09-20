<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sla_days')->default(14);
            $table->unsignedSmallInteger('aging_green_max')->default(2);
            $table->unsignedSmallInteger('aging_yellow_max')->default(6);
            $table->unsignedSmallInteger('aging_orange_max')->default(13);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_settings');
    }
};
