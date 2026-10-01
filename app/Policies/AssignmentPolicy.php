<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    /**
     * Lihat daftar assignment (mis. preview pembagian tugas hari ini).
     */
    public function viewAny(User $user): bool
    {
        return $user->role?->name === 'admin';
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return match ($user->role?->name) {
            'admin' => true,
            'petugas' => $assignment->officer?->user_id === $user->id,
            default => false,
        };
    }

    /**
     * Ubah assignment manual (FR-06: pindah petugas, tambah, kurangi).
     * Generate assignment harian pakai Gate 'generateAssignment' terpisah
     * karena itu aksi massal, bukan per-record.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $user->role?->name === 'admin';
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->role?->name === 'admin';
    }
}
