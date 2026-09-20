<?php

use Illuminate\Support\Facades\Route;

/**
 * Route khusus modul IDMS, dipisah dari web.php bawaan Breeze supaya
 * gampang dibagi per anggota tim. File ini di-require dari routes/web.php
 * (lihat instruksi di README).
 *
 * Controller di bawah ini BELUM dibuat — silakan ganti dengan controller
 * asli begitu setiap modul mulai dikerjakan. Untuk sementara bisa
 * dikomentari dulu bagian yang controller-nya belum ada supaya
 * `php artisan route:list` tidak error.
 */
Route::middleware(['auth'])->group(function () {

    // ===== Modul: Monitoring & Analitik (Orang 1) =====
    Route::middleware('role:admin,supervisor,super_admin')->group(function () {
        // Route::get('/control-tower', [ControlTowerController::class, 'index'])->name('control-tower');
        // Route::get('/laporan-aging', [AgingReportController::class, 'index'])->name('aging.index');
    });

    // ===== Modul: Data & Distribusi Tugas (Orang 2) =====
    Route::middleware('role:admin,super_admin')->group(function () {
        // Route::resource('master-data-pa', PaOrderController::class);
        // Route::resource('master-petugas', OfficerController::class);
        // Route::get('/auto-assignment', [AssignmentController::class, 'index'])->name('assignment.index');
        // Route::post('/auto-assignment/generate', [AssignmentController::class, 'generate'])->name('assignment.generate');
    });

    // ===== Modul: Pengalaman Petugas Lapangan (Orang 3) =====
    Route::middleware('role:petugas')->group(function () {
        // Route::get('/tugas-saya', [PetugasTaskController::class, 'index'])->name('petugas.tasks');
        // Route::get('/tugas-saya/{paOrder}', [PetugasTaskController::class, 'show'])->name('petugas.tasks.show');
        // Route::post('/tugas-saya/{paOrder}/selesai', [PetugasTaskController::class, 'complete'])->name('petugas.tasks.complete');
        // Route::post('/tugas-saya/{paOrder}/kendala', [PetugasTaskController::class, 'reportKendala'])->name('petugas.tasks.kendala');
        // Route::get('/riwayat', [PetugasTaskController::class, 'history'])->name('petugas.history');
    });
});
