<?php

namespace App\Providers;

use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Officer;
use App\Models\KantorPerwakilan;
use App\Models\FatPoint;
use App\Models\Splitter;
use App\Models\KendalaReason;
use App\Models\QcRejectReason;
use App\Models\SlaSetting;
use App\Observers\MasterDataAuditObserver;
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
        foreach ([Region::class, Officer::class, KantorPerwakilan::class, FatPoint::class, Splitter::class, KendalaReason::class, QcRejectReason::class, SlaSetting::class] as $model) {
            $model::observe(MasterDataAuditObserver::class);
        }

        RateLimiter::for('login', function (Request $request) {
            $identifier = strtolower(trim((string) $request->input('identifier')));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });

        // Blueprint terbaru: hanya ada 2 role aktif, Admin / PIC dan Petugas.
        // Artefak legacy yang mengacu ke supervisor/super_admin dibuang.

        // ---- Ability yang tidak terikat ke satu record model ----

        // FR-02: Upload Excel PA
        Gate::define('uploadPa', fn ($user) => $user->role?->name === 'admin');

        // FR-05: Generate assignment harian (aksi massal, beda dari
        // AssignmentPolicy@update yang mengubah satu assignment)
        Gate::define('generateAssignment', fn ($user) => $user->role?->name === 'admin');

        // FR-10, FR-11: Dashboard & progres wilayah
        Gate::define('viewDashboard', fn ($user) => $user->role?->name === 'admin');

        // FR-12: Export Excel
        Gate::define('exportReport', fn ($user) => $user->role?->name === 'admin');

        // Master data pendukung -- admin only, tidak butuh Policy per-record
        // karena tidak ada aturan kepemilikan seperti PA/Assignment/Officer.
        Gate::define('manageRegions', fn ($user) => $user->role?->name === 'admin');
        Gate::define('manageNetworkAssets', fn ($user) => $user->role?->name === 'admin');
        Gate::define('manageKendalaReasons', fn ($user) => $user->role?->name === 'admin');
        Gate::define('manageQcRejectReasons', fn ($user) => $user->role?->name === 'admin');
        Gate::define('manageSettings', fn ($user) => $user->role?->name === 'admin');
    }
}
