<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Pembungkus tipis di atas model AuditLog supaya semua tempat yang perlu
 * mencatat aktivitas (Observer, Controller, Job) menulis dengan format
 * yang sama, dan supaya gampang diganti (mis. dikirim ke log driver lain)
 * tanpa mengubah pemanggilnya satu-satu.
 */
class AuditLogger
{
    public static function log(string $action, string $entity, ?int $entityId, ?string $detail = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'detail' => $detail ?? 'No detail provided.',
            'created_at' => now(),
        ]);
    }

    public static function logWorkflow(string $action, PaOrder $paOrder, string $detail): AuditLog
    {
        return self::log($action, 'PaOrder', $paOrder->id, $detail);
    }
}
