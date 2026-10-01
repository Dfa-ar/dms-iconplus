<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaOrder;
use App\Models\QcRejectReason;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class QcController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) ($request->input('per_page', 15) ?: 15), 50);

        $query = PaOrder::with(['region', 'currentOfficer.user', 'evidences'])
            ->where(function ($query) {
                $query->where('current_status', PaOrder::STATUS_DONE)
                    ->orWhere('qc_status', '!=', null);
            });

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->input('region_id'));
        }

        $paOrders = $query->latest('updated_at')
            ->paginate($perPage)
            ->appends($request->query());

        $reasons = QcRejectReason::where('is_active', true)->orderBy('label')->get();
        $regions = Region::orderBy('kabupaten_kota')->get();

        return view('admin.qc.index', compact('paOrders', 'reasons', 'regions'));
    }

    public function review(Request $request, PaOrder $paOrder)
    {
        $validated = $request->validate($this->reviewRules());
        $outcome = $this->reviewOutcome($paOrder, $validated);

        if (isset($outcome['error'])) {
            return Redirect::route('admin.qc.index')->withErrors($outcome['error']);
        }

        $this->persistReview($request, $paOrder, $validated, $outcome);

        return Redirect::route('admin.qc.index')->with('success', $validated['decision'] === 'approve'
            ? 'PA telah lolos QC.'
            : 'PA ditolak QC dan perlu revisi.');
    }

    public function bulkReview(Request $request)
    {
        $validated = $request->validate(array_merge($this->reviewRules(), [
            'pa_ids' => ['required', 'array', 'min:1', 'max:50'],
            'pa_ids.*' => ['required', 'integer', 'distinct', 'exists:pa_orders,id'],
        ]));
        $paOrders = PaOrder::query()->whereIn('id', $validated['pa_ids'])->get()->keyBy('id');
        $outcomes = [];

        foreach ($validated['pa_ids'] as $paId) {
            $paOrder = $paOrders->get($paId);
            $outcome = $this->reviewOutcome($paOrder, $validated);

            if (isset($outcome['error'])) {
                $errorMessage = $outcome['error']['qc_checks'] ?? $outcome['error']['reason_id'] ?? 'Checklist QC tidak valid.';

                return Redirect::route('admin.qc.index')
                    ->withErrors(['pa_ids' => "PA {$paOrder->pa_number}: {$errorMessage}"])
                    ->withInput();
            }

            $outcomes[$paId] = $outcome;
        }

        DB::transaction(function () use ($request, $validated, $paOrders, $outcomes) {
            foreach ($validated['pa_ids'] as $paId) {
                $this->persistReview($request, $paOrders->get($paId), $validated, $outcomes[$paId]);
            }
        });

        $count = count($validated['pa_ids']);

        return Redirect::route('admin.qc.index')->with('success', "QC {$validated['decision']} tersimpan untuk {$count} PA.");
    }

    private function reviewRules(): array
    {
        $rules = [
            'decision' => ['required', 'in:approve,reject'],
            'qc_note' => ['nullable', 'string', 'max:1000'],
            'reason_id' => ['nullable', 'exists:qc_reject_reasons,id'],
            'qc_checks' => ['required', 'array'],
        ];

        foreach (array_keys(PaOrder::blueprintQcChecklist()) as $key) {
            $rules["qc_checks.{$key}"] = ['required', 'boolean'];
        }

        return $rules;
    }

    private function reviewOutcome(PaOrder $paOrder, array $validated): array
    {
        $qcChecklist = PaOrder::normalizeQcChecklist($validated['qc_checks']);
        $failedChecks = collect($qcChecklist)
            ->filter(fn ($passed) => ! $passed)
            ->keys()
            ->all();
        $missingEvidenceChecks = [];
        $requiredEvidence = [
            'k3_awal' => 'k3_awal',
            'ont_depan' => 'ont_depan',
            'sn_ont' => 'ont_belakang_sn',
            'fat' => 'fat_terdekat',
            'k3_akhir' => 'k3_akhir',
            'ba_pengambilan' => 'ba_pengambilan_perangkat',
        ];

        foreach ($requiredEvidence as $check => $evidenceType) {
            if (! $paOrder->evidences()->where('type', $evidenceType)->exists()) {
                $missingEvidenceChecks[] = $check;
            }
        }

        if (
            blank($paOrder->id_pln)
            || $paOrder->id_pln_confirmed !== true
            || blank($paOrder->kwh_status)
            || blank($paOrder->kwh_note)
            || blank($paOrder->kabel_panjang_meter)
            || blank($paOrder->fat_point_id)
            || blank($paOrder->splitter_id)
            || blank($paOrder->port_number)
            || ($paOrder->kwh_status === 'ADA' && ! $paOrder->evidences()->where('type', 'kwh_meter')->exists())
        ) {
            $missingEvidenceChecks[] = 'id_pln_kwh';
        }

        if ($validated['decision'] === 'approve' && $failedChecks === [] && $missingEvidenceChecks !== []) {
            return ['error' => ['qc_checks' => 'QC Lolos memerlukan semua kategori dinyatakan sesuai dan bukti tiap langkah tersedia.']];
        }

        if ($validated['decision'] === 'reject' && $failedChecks === []) {
            return ['error' => ['qc_checks' => 'Tandai kategori yang gagal sebelum menolak QC.']];
        }

        $isRejected = $validated['decision'] === 'reject' || $failedChecks !== [];
        if ($isRejected && empty($validated['reason_id'])) {
            return ['error' => ['reason_id' => 'Alasan penolakan wajib dipilih.']];
        }

        return [
            'checklist' => $qcChecklist,
            'failed_checks' => $failedChecks,
            'rejected' => $isRejected,
            'missing_evidence_checks' => $missingEvidenceChecks,
        ];
    }

    private function persistReview(Request $request, PaOrder $paOrder, array $validated, array $outcome): void
    {
        $previousStatus = $paOrder->current_status;
        $isRejected = $outcome['rejected'];
        $snUnreadable = ! $outcome['checklist']['sn_ont'];
        $toStatus = $isRejected ? PaOrder::QC_STATUS_REJECTED : PaOrder::QC_STATUS_PASSED;
        $rejectMessage = $snUnreadable
            ? 'SN ONT tidak terbaca.'
            : 'Checklist QC gagal: ' . implode(', ', array_map(
                fn ($key) => PaOrder::blueprintQcChecklist()[$key] ?? $key,
                $outcome['failed_checks']
            ));

        $paOrder->update([
            'qc_status' => $isRejected ? PaOrder::QC_STATUS_REJECTED : PaOrder::QC_STATUS_PASSED,
            'qc_note' => $validated['qc_note'] ?? null,
            'qc_reject_reason_id' => $isRejected
                ? (QcRejectReason::where('code', 'sn_ont_tidak_terbaca')->value('id') ?? $validated['reason_id'])
                : null,
            'current_status' => $toStatus,
            'qc_checklist' => $outcome['checklist'],
        ]);

        $note = $isRejected ? $rejectMessage : ($validated['qc_note'] ?? 'QC review disetujui.');
        $paOrder->statusLogs()->create([
            'from_status' => $previousStatus,
            'to_status' => $toStatus,
            'changed_by' => $request->user()->id,
            'note' => $note,
            'changed_at' => now(),
        ]);

        \App\Services\AuditLogger::log(
            $isRejected ? 'qc_rejected' : 'qc_reviewed',
            'PaOrder',
            $paOrder->id,
            "QC {$validated['decision']} untuk PA {$paOrder->pa_number}: {$note}"
        );
    }
}
