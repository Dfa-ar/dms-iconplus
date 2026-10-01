<?php

namespace App\Policies;

use App\Models\PaOrder;
use App\Models\User;

class PaOrderPolicy
{
    /**
     * Lihat daftar PA (index).
     * Admin: semua data. Petugas: hanya PA miliknya sendiri, controller
     * WAJIB scope query-nya ke PA miliknya sendiri (lihat catatan di README).
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->name, ['admin', 'petugas'], true);
    }

    /**
     * Lihat detail satu PA.
     * Aturan Blueprint 11.3: "Petugas tidak dapat melihat atau mengubah
     * PA milik petugas lain."
     */
    public function view(User $user, PaOrder $paOrder): bool
    {
        return match ($user->role?->name) {
            'admin' => true,
            'petugas' => $this->isOwner($user, $paOrder),
            default => false,
        };
    }

    /**
     * Upload Excel PA (dicek sebagai ability terpisah, bukan per-record
     * karena belum ada record saat upload). Lihat Gate 'uploadPa' di
     * AppServiceProvider.
     */

    /**
     * Ubah assignment / koreksi data PA secara manual (FR-06).
     * Hanya Admin. Ini juga ability yang dipakai untuk mengoreksi PA
     * yang sudah DONE (Blueprint 11.3: "PA berstatus DONE tidak dapat
     * diubah petugas; koreksi hanya oleh Admin dan tercatat di audit log").
     */
    public function update(User $user, PaOrder $paOrder): bool
    {
        return $user->role?->name === 'admin';
    }

    public function delete(User $user, PaOrder $paOrder): bool
    {
        return $user->role?->name === 'admin';
    }

    /**
     * Petugas menekan tombol "Mulai" (FR-08).
     * Hanya untuk PA miliknya sendiri, dan hanya dari status ASSIGNED.
     */
    public function start(User $user, PaOrder $paOrder): bool
    {
        return $user->role?->name === 'petugas'
            && $this->isOwner($user, $paOrder)
            && $paOrder->current_status === PaOrder::STATUS_ASSIGNED;
    }

    /**
     * Petugas menekan tombol "Selesai" (FR-08).
     * PA sudah DONE tidak boleh disentuh lagi oleh petugas -- itulah
     * kenapa status yang diizinkan hanya ASSIGNED/ON_PROGRESS, bukan DONE.
     */
    public function complete(User $user, PaOrder $paOrder): bool
    {
        return $user->role?->name === 'petugas'
            && $this->isOwner($user, $paOrder)
            && in_array($paOrder->current_status, [
                PaOrder::STATUS_ASSIGNED,
                PaOrder::STATUS_ON_PROGRESS,
            ], true);
    }

    /**
     * Petugas melaporkan kendala (FR-09).
     */
    public function reportKendala(User $user, PaOrder $paOrder): bool
    {
        return $user->role?->name === 'petugas'
            && $this->isOwner($user, $paOrder)
            && in_array($paOrder->current_status, [
                PaOrder::STATUS_ASSIGNED,
                PaOrder::STATUS_ON_PROGRESS,
            ], true);
    }

    /**
     * Helper: apakah PA ini sedang ditugaskan ke petugas yang login.
     * current_officer_id di pa_orders -> officers.user_id -> users.id
     */
    private function isOwner(User $user, PaOrder $paOrder): bool
    {
        return $paOrder->currentOfficer !== null
            && $paOrder->currentOfficer->user_id === $user->id;
    }
}
