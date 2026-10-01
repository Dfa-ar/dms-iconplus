<?php

// Tambahkan require __DIR__.'/idms-security.php'; di routes/web.php kamu,
// SETELAH require __DIR__.'/idms.php'; (paket idms-controller-starter).

use App\Http\Controllers\EvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Blueprint 14: foto bukti diakses lewat URL yang diotorisasi.
    // Otorisasi per-record dicek di dalam EvidenceController::show lewat
    // PaOrderPolicy::view (evidence->paOrder), bukan lewat middleware role
    // di sini -- karena admin dan petugas pemilik PA sama-sama
    // boleh akses, tapi petugas lain tidak.
    Route::get('/evidence/{evidence}', [EvidenceController::class, 'show'])->name('evidence.show');
});
