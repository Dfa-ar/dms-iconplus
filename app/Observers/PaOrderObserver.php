<?php

namespace App\Observers;

use App\Models\PaOrder;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Ini "jaring pengaman" otomatis: apapun cara PA yang sudah DONE diubah
 * (lewat CorrectPaRequest, tinker, command, dsb), selama dilakukan oleh
 * user yang login, perubahannya tetap tercatat di audit_logs. Ini
 * melengkapi (bukan menggantikan) pencatatan eksplisit yang lebih
 * deskriptif di Controller (lihat routes/web-example.php), karena
 * Observer ini tidak tahu "alasan" koreksi -- hanya tahu APA yang berubah.
 */
class PaOrderObserver
{
    public function updating(PaOrder $paOrder): void
    {
        $user = Auth::user();

        if (! $user) {
            // Perubahan dari seeder/command/job tanpa user login -- lewati,
            // tidak ada siapa-siapa untuk dicatat sebagai pelaku.
            return;
        }

        $wasDone = $paOrder->getOriginal('current_status') === PaOrder::STATUS_DONE;

        if ($wasDone && $paOrder->isDirty()) {
            AuditLogger::log(
                action: 'correct_done_pa',
                entity: 'PaOrder',
                entityId: $paOrder->id,
                detail: sprintf(
                    'PA %s dikoreksi oleh %s (role: %s). Field berubah: %s',
                    $paOrder->pa_number,
                    $user->name,
                    $user->role?->name ?? '-',
                    json_encode($paOrder->getDirty())
                ),
            );
        }
    }
}
