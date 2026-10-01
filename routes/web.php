<?php

// Gabungkan potongan yang relevan ke routes/web.php project kamu.
// Controller yang dipanggil di sini SUDAH nyata (lihat app/Http/Controllers/),
// tinggal disesuaikan dengan route Auth/Breeze yang sudah ada di project kamu.

use App\Http\Controllers\Admin\AgingReportController;
use App\Http\Controllers\Admin\AccountManagementController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\BastController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\FatPointController;
use App\Http\Controllers\Admin\KantorPerwakilanController;
use App\Http\Controllers\Admin\KendalaReasonController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\OfficerController;
use App\Http\Controllers\Admin\PaOrderController;
use App\Http\Controllers\Admin\PaUploadController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QcController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\QcRejectReasonController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SplitterController;
use App\Http\Controllers\Petugas\TaskController;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\EvidenceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $homeRoute = auth()->user()->role?->name === 'petugas'
        ? 'petugas.tasks.index'
        : 'dashboard';

    return redirect()->route($homeRoute);
});

Route::middleware('auth')->group(function () {
    Route::get('/evidence/{evidence}', [EvidenceController::class, 'show'])->name('evidence.show');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', function (Request $request) {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) $request->input('identifier'));
        $user = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhereHas('officer', fn ($query) => $query->where('employee_code', $identifier));
            })
            ->first();

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $request->input('password')])) {
            return back()->withErrors([
                'identifier' => 'Email/NIP atau kata sandi salah.',
            ])->onlyInput('identifier');
        }

        $request->session()->regenerate();

        $redirectRoute = match ($user->role?->name) {
            'petugas' => 'petugas.tasks.index',
            'admin' => 'dashboard',
            default => 'dashboard',
        };

        return redirect()->route($redirectRoute);
    })->middleware('throttle:login')->name('login.submit');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    // ---- Admin / PIC ----
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pa/upload', [PaUploadController::class, 'create'])->name('pa.upload');
        Route::get('/pa/upload/template', [PaUploadController::class, 'template'])->name('pa.upload.template');
        Route::get('/pa/upload-history', [PaUploadController::class, 'history'])->name('pa.upload-history');
        Route::get('/pa/upload-history/{uploadBatch}/errors', [PaUploadController::class, 'downloadErrors'])->name('pa.upload-history.errors');
        Route::post('/pa/upload/preview', [PaUploadController::class, 'preview'])->name('pa.upload.preview');
        Route::post('/pa/upload', [PaUploadController::class, 'store'])->name('pa.upload');
        Route::patch('/pa/{paOrder}/correct', [PaOrderController::class, 'correct'])->name('pa.correct');

        Route::resource('petugas', OfficerController::class)
            ->except(['show'])
            ->parameters(['petugas' => 'officer']);
        Route::get('/accounts', [AccountManagementController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/{officer}', [AccountManagementController::class, 'store'])->name('accounts.store');
        Route::patch('/accounts/{officer}', [AccountManagementController::class, 'update'])->name('accounts.update');
        Route::resource('kantor-perwakilan', KantorPerwakilanController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kantor-perwakilan' => 'kantorPerwakilan']);
        Route::resource('regions', RegionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('fat-points', FatPointController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['fat-points' => 'fatPoint']);
        Route::resource('splitters', SplitterController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['splitters' => 'splitter']);
        Route::resource('kendala-reasons', KendalaReasonController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kendala-reasons' => 'kendalaReason']);
        Route::get('/qc-reject-reasons', [QcRejectReasonController::class, 'index'])->name('qc-reject-reasons.index');
        Route::post('/qc-reject-reasons', [QcRejectReasonController::class, 'store'])->name('qc-reject-reasons.store');
        Route::patch('/qc-reject-reasons/{qcRejectReason}', [QcRejectReasonController::class, 'update'])->name('qc-reject-reasons.update');
        Route::delete('/qc-reject-reasons/{qcRejectReason}', [QcRejectReasonController::class, 'destroy'])->name('qc-reject-reasons.destroy');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

        Route::post('/assignments/generate', [AssignmentController::class, 'generate'])->name('assignments.generate');
        Route::post('/assignments/{officer}/remind', [AssignmentController::class, 'remind'])->name('assignments.remind');
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::patch('/assignments/{assignment}', [AssignmentController::class, 'reassign'])->name('assignments.reassign');

        Route::get('/qc', [QcController::class, 'index'])->name('qc.index');
        Route::post('/qc/{paOrder}/review', [QcController::class, 'review'])->name('qc.review');
        Route::post('/qc/bulk-review', [QcController::class, 'bulkReview'])->name('qc.bulk-review');

        Route::get('/bast', [BastController::class, 'index'])->name('bast.index');
        Route::post('/bast/generate', [BastController::class, 'generate'])->name('bast.generate');
        Route::post('/bast/{bastDocument}/void', [BastController::class, 'void'])->name('bast.void');
        Route::get('/bast/{bastDocument}/preview', [BastController::class, 'preview'])->name('bast.preview');
        Route::get('/bast/{bastDocument}/download', [BastController::class, 'download'])->name('bast.download');

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/batches', [PaymentController::class, 'storeBatch'])->name('payments.batches.store');
        Route::get('/payments/batches/{paymentBatch}/proof', [PaymentController::class, 'proof'])->name('payments.batches.proof');
        Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
        Route::post('/payments/{paOrder}', [PaymentController::class, 'update'])->name('payments.update');

        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/xlsx', [ReportsController::class, 'export'])->name('reports.xlsx');
    });

    // ---- Admin / PIC: dashboard, laporan, dan data operasional ----
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/regions', [DashboardController::class, 'regions'])->name('dashboard.regions');
        Route::get('/dashboard/kendala', [DashboardController::class, 'kendala'])->name('dashboard.kendala');
        Route::get('/dashboard/map', [DashboardController::class, 'map'])->name('admin.map.index');
        Route::get('/dashboard/map-summary', [DashboardController::class, 'mapSummary'])->name('admin.map.summary');
        Route::get('/reports/export', [DashboardController::class, 'export'])->name('reports.export');
        Route::get('/pa', [PaOrderController::class, 'index'])->name('admin.pa.index');
        Route::get('/pa/{paOrder}', [PaOrderController::class, 'show'])->name('admin.pa.show');

        Route::get('/laporan/aging', [AgingReportController::class, 'index'])->name('laporan.aging');
        Route::get('/laporan/aging/export', [AgingReportController::class, 'export'])->name('laporan.aging.export');
    });

    // ---- Petugas: hanya tugas miliknya ----
    Route::middleware('role:petugas')->prefix('my')->name('petugas.')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{paOrder}', [TaskController::class, 'show'])->name('tasks.show');
        Route::post('/tasks/{paOrder}/start', [TaskController::class, 'start'])->name('tasks.start');
        Route::post('/tasks/{paOrder}/close-icrm', [TaskController::class, 'closeIcrm'])->name('tasks.close-icrm');
        Route::post('/tasks/{paOrder}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::post('/tasks/{paOrder}/kendala', [TaskController::class, 'kendala'])->name('tasks.kendala');
        Route::get('/riwayat', [TaskController::class, 'history'])->name('tasks.history');
        Route::get('/profil', [TaskController::class, 'profile'])->name('profile');
    });

});

/*
|--------------------------------------------------------------------------
| Contoh di Blade (dipakai untuk sembunyikan tombol, BUKAN pengganti
| authorize()/Form Request di Controller -- pengecekan sesungguhnya tetap
| di server)
|--------------------------------------------------------------------------
|
| @can('update', $paOrder)
|     <button>Ubah Assignment</button>
| @endcan
|
| @can('generateAssignment')
|     <form method="POST" action="{{ route('admin.assignments.generate') }}">
|         @csrf
|         <select name="region_id">...</select>
|         <input type="number" name="target_per_officer" value="20">
|         <button>Generate Tugas Hari Ini</button>
|     </form>
| @endcan
*/
