<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bast_documents', function (Blueprint $table) {
            $table->string('pihak_menyerahkan')->nullable()->after('notes');
            $table->string('pihak_menerima')->nullable()->after('pihak_menyerahkan');
            $table->string('jabatan_menyerahkan')->nullable()->after('pihak_menerima');
            $table->string('jabatan_menerima')->nullable()->after('jabatan_menyerahkan');
            $table->string('lokasi')->nullable()->after('jabatan_menerima');
        });
    }

    public function down(): void
    {
        Schema::table('bast_documents', function (Blueprint $table) {
            $table->dropColumn(['pihak_menyerahkan', 'pihak_menerima', 'jabatan_menyerahkan', 'jabatan_menerima', 'lokasi']);
        });
    }
};
