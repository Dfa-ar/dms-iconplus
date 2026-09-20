<?php

// Ini CONTOH pemakaian -- gabungkan potongan yang relevan ke routes/web.php
// project kamu, jangan copy file ini apa adanya menimpa routes/web.php.

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\OfficerController;
use App\Http\Controllers\Admin\PaUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Petugas\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // ---- Admin / PIC ----
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/pa/upload', [PaUploadController::class, 'store']);
        Route::post('/assignments/generate', [AssignmentController::class, 'generate']);
        Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::resource('petugas', OfficerController::class);
    });

    // ---- Admin & Supervisor: dashboard + laporan (read only utk supervisor) ----
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/regions', [DashboardController::class, 'regions']);
        Route::get('/dashboard/kendala', [DashboardController::class, 'kendala']);
        Route::get('/reports/export', [DashboardController::class, 'export']);
    });

    // ---- Petugas: hanya tugas miliknya ----
    Route::middleware('role:petugas')->prefix('my')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{paOrder}', [TaskController::class, 'show']);   // dicek lagi via Policy::view
        Route::post('/tasks/{paOrder}/start', [TaskController::class, 'start']);       // Policy::start
        Route::post('/tasks/{paOrder}/complete', [TaskController::class, 'complete']); // Policy::complete
        Route::post('/tasks/{paOrder}/kendala', [TaskController::class, 'kendala']);   // Policy::reportKendala
    });

    // ---- Super Admin ----
    Route::middleware('role:super_admin')->prefix('system')->group(function () {
        // Route::resource('users', UserController::class);
        // Route::get('/sla-settings', [SlaSettingController::class, 'edit']);
        // Route::get('/audit-log', [AuditLogController::class, 'index']);
    });
});

/*
|--------------------------------------------------------------------------
| Contoh isi Controller: gabungan middleware (blokir menu) + Policy
| (blokir per-record). Middleware 'role:petugas' di atas cukup memastikan
| non-petugas tidak bisa akses /my/tasks sama sekali. Policy di bawah ini
| yang memastikan petugas A tidak bisa buka detail/aksi PA milik petugas B.
|--------------------------------------------------------------------------
*/

// class TaskController extends Controller
// {
//     public function index(Request $request)
//     {
//         $this->authorize('viewAny', PaOrder::class);
//
//         // WAJIB: scope ke PA milik petugas yang login
//         $tasks = PaOrder::whereHas('currentOfficer', function ($q) use ($request) {
//             $q->where('user_id', $request->user()->id);
//         })->get();
//
//         return view('petugas.dashboard', compact('tasks'));
//     }
//
//     public function show(PaOrder $paOrder)
//     {
//         $this->authorize('view', $paOrder);
//         return view('petugas.tugas.show', compact('paOrder'));
//     }
//
//     public function start(PaOrder $paOrder)
//     {
//         $this->authorize('start', $paOrder);
//         $paOrder->update([
//             'current_status' => PaOrder::STATUS_ON_PROGRESS,
//             'started_at' => now(),
//         ]);
//         return back();
//     }
//
//     public function complete(CompletePaRequest $request, PaOrder $paOrder)
//     {
//         // Otorisasi sudah dicek di dalam CompletePaRequest::authorize(),
//         // tidak perlu $this->authorize(...) lagi di sini.
//         $data = $request->validated();
//
//         foreach (['foto_perangkat' => 'perangkat', 'foto_modem_ont' => 'modem_ont', 'foto_serah_terima' => 'serah_terima'] as $field => $type) {
//             $path = $request->file($field)->store('evidence', 'public');
//             $paOrder->evidences()->create([
//                 'type' => $type,
//                 'file_path' => $path,
//                 'uploaded_by' => $request->user()->id,
//                 'receiver_name' => $data['receiver_name'],
//                 'pickup_time' => $data['pickup_time'] ?? now(),
//             ]);
//         }
//
//         $paOrder->update([
//             'current_status' => PaOrder::STATUS_DONE,
//             'completed_at' => now(),
//             'notes' => $data['notes'] ?? $paOrder->notes,
//         ]);
//
//         $paOrder->statusLogs()->create([
//             'from_status' => PaOrder::STATUS_ON_PROGRESS,
//             'to_status' => PaOrder::STATUS_DONE,
//             'changed_by' => $request->user()->id,
//             'changed_at' => now(),
//         ]);
//
//         return back();
//     }
//
//     public function kendala(KendalaPaRequest $request, PaOrder $paOrder)
//     {
//         $data = $request->validated();
//         $path = $request->file('foto_kendala')->store('evidence', 'public');
//
//         $paOrder->evidences()->create([
//             'type' => 'kendala',
//             'file_path' => $path,
//             'uploaded_by' => $request->user()->id,
//         ]);
//
//         $paOrder->update([
//             'current_status' => PaOrder::STATUS_KENDALA,
//             'kendala_reason_id' => $data['kendala_reason_id'],
//             'notes' => $data['notes'],
//         ]);
//
//         return back();
//     }
// }

// class PaOrderAdminController extends Controller
// {
//     // Koreksi Admin, termasuk PA yang sudah DONE (Blueprint 11.3).
//     // PaOrderObserver otomatis mencatat "APA yang berubah"; di sini kita
//     // tambahkan log eksplisit berisi "KENAPA" (correction_reason) supaya
//     // audit log lebih bisa dibaca manusia, bukan cuma dump field.
//     public function correct(CorrectPaRequest $request, PaOrder $paOrder)
//     {
//         $data = $request->validated();
//
//         $paOrder->update([
//             'current_status' => $data['current_status'],
//         ]);
//
//         AuditLogger::log(
//             action: 'correct_done_pa_reason',
//             entity: 'PaOrder',
//             entityId: $paOrder->id,
//             detail: $data['correction_reason'],
//         );
//
//         return back();
//     }
// }

/*
|--------------------------------------------------------------------------
| Contoh di Blade (dipakai untuk sembunyikan tombol, BUKAN pengganti
| authorize() di Controller -- pengecekan sesungguhnya tetap di server)
|--------------------------------------------------------------------------
|
| @can('update', $paOrder)
|     <button>Ubah Assignment</button>
| @endcan
|
| @can('generateAssignment')
|     <button>Generate Tugas Hari Ini</button>
| @endcan
*/
