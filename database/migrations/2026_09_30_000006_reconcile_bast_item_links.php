<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('bast_items')->orderBy('id')->get() as $item) {
            $paOrder = DB::table('pa_orders')->where('id', $item->pa_id)->first();
            if (! $paOrder) {
                continue;
            }

            $nextStatus = $paOrder->payment_status === 'PAID' ? 'PAID' : 'BAST_ISSUED';
            DB::table('pa_orders')->where('id', $item->pa_id)->update([
                'bast_document_id' => $item->bast_document_id,
                'current_status' => $nextStatus,
            ]);
        }

        $archivedDocumentIds = DB::table('bast_item_archives')
            ->select('bast_document_id')
            ->distinct()
            ->pluck('bast_document_id');

        foreach ($archivedDocumentIds as $documentId) {
            $hasActiveItems = DB::table('bast_items')->where('bast_document_id', $documentId)->exists();
            if ($hasActiveItems) {
                continue;
            }

            $document = DB::table('bast_documents')->where('id', $documentId)->first();
            if (! $document || $document->status !== 'FINAL') {
                continue;
            }

            DB::table('bast_documents')->where('id', $documentId)->update([
                'status' => 'VOID',
                'void_reason' => 'Item PA duplikat diarsipkan; PA dipertahankan pada BAST lain yang lebih awal.',
                'voided_by' => $document->created_by,
                'voided_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $archivedDocumentIds = DB::table('bast_item_archives')
            ->select('bast_document_id')
            ->distinct()
            ->pluck('bast_document_id');

        foreach ($archivedDocumentIds as $documentId) {
            $document = DB::table('bast_documents')->where('id', $documentId)->first();
            if (! $document || ! str_starts_with((string) $document->void_reason, 'Item PA duplikat diarsipkan;')) {
                continue;
            }

            foreach (DB::table('bast_item_archives')->where('bast_document_id', $documentId)->get() as $item) {
                $paOrder = DB::table('pa_orders')->where('id', $item->pa_id)->first();
                DB::table('pa_orders')->where('id', $item->pa_id)->update([
                    'bast_document_id' => $documentId,
                    'current_status' => $paOrder?->payment_status === 'PAID' ? 'PAID' : 'BAST_ISSUED',
                ]);
            }

            DB::table('bast_documents')->where('id', $documentId)->update([
                'status' => 'FINAL',
                'void_reason' => null,
                'voided_by' => null,
                'voided_at' => null,
            ]);
        }
    }
};