<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;

/**
 * Blueprint 14: "Foto bukti diakses melalui URL yang diotorisasi, bukan
 * folder publik." Dipakai bersama oleh Admin dan Petugas
 * (pemilik PA-nya) -- otorisasi memakai PaOrderPolicy::view lewat relasi
 * evidence->paOrder, jadi tidak perlu EvidencePolicy terpisah karena
 * aturannya sama persis dengan "siapa boleh lihat PA ini".
 */
class EvidenceController extends Controller
{
    public function show(Evidence $evidence)
    {
        $this->authorize('view', $evidence->paOrder);

        $filePath = ltrim((string) $evidence->file_path, '/');
        $normalizedPath = preg_replace('#^public/#', '', $filePath) ?? $filePath;

        abort_unless(
            Storage::disk('public')->exists($normalizedPath),
            404,
            'File evidence tidak ditemukan.'
        );

        return Storage::disk('public')->response($normalizedPath);
    }
}
