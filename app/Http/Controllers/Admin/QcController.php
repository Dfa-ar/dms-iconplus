<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaOrder;
use App\Models\QcRejectReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class QcController extends Controller
{
    protected function qcFailureReasons(array $failedChecks, bool $snOntReadable = false): array
    {
        $reasons = [];

        if ($snOntReadable) {
            $reasons[] = 'SN ONT tidak terbaca';
        }

        foreach ($failedChecks as $key) {
            if ($key === 'sn_ont') {
                $reasons[] = 'SN ONT tidak terbaca';
                continue;
            }

            $reasons[] = PaOrder::blueprintQcChecklist()[$key] ?? $key;
        }

        return array_values(array_unique($reasons));
    }

    public function index(Request $request)
    {
        $perPage = min((int) ($request->input('per_page', 15) ?: 15), 50);

        $paOrders = PaOrder::with(['region', 'currentOfficer.user', 'evidences'])
            ->where(function ($query) {
                $query->where('current_status', PaOrder::STATUS_DONE)
                    ->orWhere('qc_status', '!=', null);
            })
            ->latest('updated_at')
            ->paginate($perPage)
            ->appends($request->query());

        $reasons = QcRejectReason::orderBy('label')->get();

        return view('admin.qc.index', compact('paOrders', 'reasons'));
    }

    public function review(Request $request, PaOrder $paOrder)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'qc_note' => 'nullable|string|max:1000',
            'reason_id' => 'nullable|exists:qc_reject_reasons,id',
            'qc_checks' => ['nullable', 'array'],
            'qc_checks.*' => ['nullable', 'boolean'],
        ]);

        $qcChecklist = array_key_exists('qc_checks', $validated)
            ? PaOrder::normalizeQcChecklist($validated['qc_checks'] ?? [])
            : array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);
        $failedChecks = collect($qcChecklist)->filter(fn ($passed) => ! $passed)->keys()->all();
        $snOntUnreadable = $paOrder->sn_ont_readable === false || (($validated['qc_checks'] ?? [])['sn_ont'] ?? null) === false || (($validated['qc_checks'] ?? [])['sn_ont'] ?? null) === '0';

        if ($validated['decision'] === 'approve' && ! array_key_exists('qc_checks', $validated)) {
            return back()->withErrors([
                'qc_checks' => 'Checklist QC wajib dikirim sebelum approval.',
            ]);
        }

        if ($validated['decision'] === 'reject' && blank($validated['reason_id'] ?? null)) {
            return back()->withErrors([
                'reason_id' => 'Alasan penolakan wajib diisi saat QC ditolak.',
            ]);
        }

        $previousStatus = $paOrder->current_status;
        $shouldReject = $validated['decision'] === 'reject';
        $newQcStatus = $shouldReject ? PaOrder::QC_STATUS_REJECTED : PaOrder::QC_STATUS_PASSED;
        $nextStatus = $newQcStatus;

        if ($shouldReject) {
            $rejectReasons = $this->qcFailureReasons(
                $failedChecks,
                $snOntUnreadable
            );

            $rejectMessage = $rejectReasons !== []
                ? 'Catatan pemeriksaan QC: ' . implode(', ', $rejectReasons)
                : 'QC ditolak oleh admin.';

            $paOrder->update([
                'qc_status' => PaOrder::QC_STATUS_REJECTED,
                'qc_note' => $validated['qc_note'] ?? 'Checklist QC belum lengkap.',
                'qc_reject_reason_id' => $validated['reason_id'] ?? null,
                'current_status' => PaOrder::QC_STATUS_REJECTED,
                'qc_checklist' => $qcChecklist,
            ]);

            $paOrder->statusLogs()->create([
                'from_status' => $previousStatus,
                'to_status' => PaOrder::QC_STATUS_REJECTED,
                'changed_by' => auth()->id(),
                'note' => $rejectMessage,
                'changed_at' => now(),
            ]);

            return Redirect::route('admin.qc.index')->with('error', 'PA ditolak QC. ' . $rejectMessage);
        }

        $paOrder->update([
            'qc_status' => $newQcStatus,
            'qc_note' => $validated['qc_note'] ?? null,
            'qc_reject_reason_id' => $validated['reason_id'] ?? null,
            'current_status' => $nextStatus,
            'qc_checklist' => $qcChecklist,
        ]);

        $paOrder->statusLogs()->create([
            'from_status' => $previousStatus,
            'to_status' => $nextStatus,
            'changed_by' => auth()->id(),
            'note' => $validated['qc_note'] ?? 'QC review',
            'changed_at' => now(),
        ]);

        \App\Services\AuditLogger::log(
            'qc_reviewed',
            'PaOrder',
            $paOrder->id,
            "QC {$validated['decision']} untuk PA {$paOrder->pa_number}: " . ($validated['qc_note'] ?? 'tanpa catatan')
        );

        return Redirect::route('admin.qc.index')->with('success', $validated['decision'] === 'approve'
            ? 'PA telah lolos QC.'
            : 'PA ditolak QC dan perlu revisi.');
    }

    public function bulkReview(Request $request)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'pa_ids' => ['required', 'array', 'min:1'],
            'pa_ids.*' => ['required', 'integer', 'exists:pa_orders,id'],
            'qc_note' => 'nullable|string|max:1000',
            'reason_id' => 'nullable|exists:qc_reject_reasons,id',
            'qc_checks' => ['nullable', 'array'],
            'qc_checks.*' => ['nullable', 'boolean'],
        ]);

        $paOrders = PaOrder::query()
            ->whereIn('id', $validated['pa_ids'])
            ->with('evidences')
            ->get();

        $requiredEvidenceCount = 6;
        $missingEvidence = $paOrders->filter(fn (PaOrder $paOrder) => $paOrder->evidences->count() < $requiredEvidenceCount)
            ->pluck('pa_number')
            ->all();

        if ($validated['decision'] === 'approve' && $missingEvidence !== []) {
            throw ValidationException::withMessages([
                'pa_ids' => 'Semua PA yang dipilih harus memiliki bukti QC lengkap sebelum di-approve massal.',
            ]);
        }

        if ($validated['decision'] === 'reject' && blank($validated['reason_id'] ?? null)) {
            throw ValidationException::withMessages([
                'reason_id' => 'Alasan penolakan wajib diisi saat QC massal ditolak.',
            ]);
        }

        $bulkChecklist = array_key_exists('qc_checks', $validated)
            ? PaOrder::normalizeQcChecklist($validated['qc_checks'] ?? [])
            : array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);

        if ($validated['decision'] === 'approve' && ! array_key_exists('qc_checks', $validated)) {
            throw ValidationException::withMessages([
                'pa_ids' => 'Checklist QC wajib dikirim sebelum approve massal.',
            ]);
        }

        DB::transaction(function () use ($paOrders, $validated, $bulkChecklist) {
            foreach ($paOrders as $paOrder) {
                $previousStatus = $paOrder->current_status;
                $nextStatus = $validated['decision'] === 'reject' ? PaOrder::QC_STATUS_REJECTED : PaOrder::QC_STATUS_PASSED;
                $snFailure = $paOrder->sn_ont_readable === false || ($bulkChecklist['sn_ont'] ?? false) === false;
                $failedChecks = collect($bulkChecklist)->filter(fn ($passed) => ! $passed)->keys()->all();
                $rejectReasons = $this->qcFailureReasons($failedChecks, $snFailure);

                $paOrder->update([
                    'qc_status' => $nextStatus,
                    'qc_note' => $validated['qc_note'] ?? null,
                    'qc_reject_reason_id' => $validated['reason_id'] ?? null,
                    'current_status' => $nextStatus,
                    'qc_checklist' => $bulkChecklist,
                ]);

                $paOrder->statusLogs()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $nextStatus,
                    'changed_by' => auth()->id(),
                    'note' => $nextStatus === PaOrder::QC_STATUS_REJECTED && $rejectReasons !== []
                        ? 'Checklist QC gagal: ' . implode(', ', $rejectReasons)
                        : ($validated['qc_note'] ?? 'QC bulk review'),
                    'changed_at' => now(),
                ]);
            }
        });

        return Redirect::route('admin.qc.index')->with('success', 'QC ' . ($validated['decision'] === 'approve' ? 'approve' : 'reject') . ' tersimpan untuk ' . count($validated['pa_ids']) . ' PA.');
    }
}
