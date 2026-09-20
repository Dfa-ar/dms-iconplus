<?php

namespace App\Providers;

use App\Models\PaOrder;
use App\Observers\PaOrderObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Audit log otomatis untuk koreksi PA yang sudah DONE (Blueprint 11.3)
        PaOrder::observe(PaOrderObserver::class);

        RateLimiter::for('login', function (Request $request) {
            $identifier = strtolower(trim((string) $request->input('identifier')));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });

        // super_admin sesuai Blueprint: "Kelola user dan role, konfigurasi
        // SLA, audit log". Di implementasi ini super_admin juga dijadikan
        // bypass penuh (bisa lakukan apapun) supaya tidak perlu didaftarkan
        // manual di tiap ability baru -- ASUMSI, ubah kalau pembimbing
        // ingin super_admin benar-benar dibatasi hanya 3 hal itu.
        Gate::before(function ($user, string $ability) {
            return $user->role?->name === 'super_admin' ? true : null;
        });

        // ---- Ability yang tidak terikat ke satu record model ----

        // FR-02: Upload Excel PA
        Gate::define('uploadPa', fn ($user) => $user->role?->name === 'admin');

        // FR-05: Generate assignment harian (aksi massal, beda dari
        // AssignmentPolicy@update yang mengubah satu assignment)
        Gate::define('generateAssignment', fn ($user) => $user->role?->name === 'admin');

        // FR-10, FR-11: Dashboard & progres wilayah
        Gate::define('viewDashboard', fn ($user) => in_array(
            $user->role?->name, ['admin', 'supervisor'], true
        ));

        // FR-12: Export Excel
        Gate::define('exportReport', fn ($user) => in_array(
            $user->role?->name, ['admin', 'supervisor'], true
        ));

        // Master data pendukung -- admin only, tidak butuh Policy per-record
        // karena tidak ada aturan kepemilikan seperti PA/Assignment/Officer.
        Gate::define('manageRegions', fn ($user) => $user->role?->name === 'admin');
        Gate::define('manageKendalaReasons', fn ($user) => $user->role?->name === 'admin');

        // Khusus Super Admin (di luar bypass Gate::before di atas, ditulis
        // eksplisit juga supaya jelas dibaca & tetap benar walau nanti
        // Gate::before dihapus)
        Gate::define('manageUsers', fn ($user) => $user->role?->name === 'super_admin');
        Gate::define('manageSlaSettings', fn ($user) => $user->role?->name === 'super_admin');
        Gate::define('viewAuditLog', fn ($user) => $user->role?->name === 'super_admin');
    }
}
