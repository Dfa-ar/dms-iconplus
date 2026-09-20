<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi route berdasarkan role. Dipakai lewat alias 'role', misal:
 *   Route::middleware(['auth', 'role:admin'])->group(...)
 *   Route::middleware(['auth', 'role:admin,supervisor'])->group(...)
 *
 * Ini pelengkap Policy, bukan pengganti -- middleware ini menolak akses
 * ke SELURUH route/menu (mis. Petugas tidak boleh buka /admin/petugas
 * sama sekali), sedangkan Policy mengatur otorisasi per-record/per-aksi
 * (mis. Petugas boleh buka /my/tasks tapi hanya lihat PA miliknya).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->is_active) {
            abort(403, 'Akun Anda tidak aktif. Hubungi Admin.');
        }

        if (! in_array($user->role?->name, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
