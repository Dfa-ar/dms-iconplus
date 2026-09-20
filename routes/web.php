<?php

// Gabungkan potongan yang relevan ke routes/web.php project kamu.
// Controller yang dipanggil di sini SUDAH nyata (lihat app/Http/Controllers/),
// tinggal disesuaikan dengan route Auth/Breeze yang sudah ada di project kamu.

use App\Http\Controllers\Admin\AgingReportController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KendalaReasonController;
use App\Http\Controllers\Admin\OfficerController;
use App\Http\Controllers\Admin\PaOrderController;
use App\Http\Controllers\Admin\PaUploadController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Petugas\TaskController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SlaSettingController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\EvidenceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
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
            'admin', 'supervisor' => 'dashboard',
            'super_admin' => 'system.users.index',
            default => 'dashboard',
        };

        return redirect()->intended(route($redirectRoute, absolute: false) ?: '/dashboard');
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
        Route::post('/pa/upload/preview', [PaUploadController::class, 'preview'])->name('pa.upload.preview');
        Route::post('/pa/upload', [PaUploadController::class, 'store'])->name('pa.upload');
        Route::patch('/pa/{paOrder}/correct', [PaOrderController::class, 'correct'])->name('pa.correct');

        Route::resource('petugas', OfficerController::class)
            ->except(['show'])
            ->parameters(['petugas' => 'officer']);
        Route::resource('regions', RegionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('kendala-reasons', KendalaReasonController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kendala-reasons' => 'kendalaReason']);

        Route::post('/assignments/generate', [AssignmentController::class, 'generate'])->name('assignments.generate');
        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::patch('/assignments/{assignment}', [AssignmentController::class, 'reassign'])->name('assignments.reassign');
    });

    // ---- Admin & Supervisor: dashboard + laporan (read only utk supervisor) ----
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/regions', [DashboardController::class, 'regions'])->name('dashboard.regions');
        Route::get('/dashboard/kendala', [DashboardController::class, 'kendala'])->name('dashboard.kendala');
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
        Route::post('/tasks/{paOrder}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::post('/tasks/{paOrder}/kendala', [TaskController::class, 'kendala'])->name('tasks.kendala');
        Route::get('/riwayat', [TaskController::class, 'history'])->name('tasks.history');
    });

    // ---- Super Admin ----
    Route::middleware('role:super_admin')->prefix('system')->name('system.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('roles', RoleController::class)->only(['index', 'store', 'destroy']);
        Route::get('/sla-settings', [SlaSettingController::class, 'edit'])->name('sla-settings.edit');
        Route::patch('/sla-settings', [SlaSettingController::class, 'update'])->name('sla-settings.update');
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
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
