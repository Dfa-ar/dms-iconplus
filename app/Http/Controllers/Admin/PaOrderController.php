<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectPaRequest;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class PaOrderController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaOrder::class);

        $query = PaOrder::with(['region', 'currentOfficer'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('pa_number', 'like', "%{$search}%")
                        ->orWhere('customer_id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('current_status', $request->input('status')))
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', $request->integer('region_id')))
            ->when($request->filled('officer_id'), fn ($query) => $query->where('current_officer_id', $request->integer('officer_id')))
            ->when($request->filled('pa_date'), fn ($query) => $query->whereDate('pa_date', $request->input('pa_date')))
            ->orderByDesc('pa_date')
            ->orderBy('pa_number');

        return view('admin.pa.index', [
            'paOrders' => $query->paginate(25)->withQueryString(),
            'regions' => Region::query()->orderBy('kabupaten_kota')->orderBy('kecamatan')->get(),
            'officers' => Officer::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => [
                PaOrder::STATUS_UNASSIGNED,
                PaOrder::STATUS_ASSIGNED,
                PaOrder::STATUS_ON_PROGRESS,
                PaOrder::STATUS_DONE,
                PaOrder::STATUS_KENDALA,
            ],
        ]);
    }

    public function show(PaOrder $paOrder)
    {
        $this->authorize('view', $paOrder);

        $paOrder->load(['region', 'currentOfficer', 'kendalaReason', 'evidences', 'statusLogs.changedBy']);

        return view('admin.pa.show', compact('paOrder'));
    }

    /**
     * Koreksi manual oleh Admin (Blueprint 11.3). PaOrderObserver otomatis
     * mencatat field apa yang berubah; di sini kita tambahkan log eksplisit
     * berisi ALASAN koreksi (correction_reason), yang tidak bisa ditebak
     * otomatis oleh Observer.
     */
    public function correct(CorrectPaRequest $request, PaOrder $paOrder)
    {
        $data = $request->validated();
        $fromStatus = $paOrder->current_status;
        $toStatus = $data['current_status'];

        // Bersihkan field yang jadi basi kalau status baru bukan lagi DONE/
        // KENDALA -- supaya perhitungan aging ($paOrder->aging pakai
        // completed_at) dan data kendala tidak menyesatkan setelah dikoreksi.
        $extra = [];
        if ($toStatus !== PaOrder::STATUS_DONE) {
            $extra['completed_at'] = null;
        }
        if ($toStatus !== PaOrder::STATUS_KENDALA) {
            $extra['kendala_reason_id'] = null;
        }
        if (! in_array($toStatus, [PaOrder::STATUS_ON_PROGRESS, PaOrder::STATUS_DONE, PaOrder::STATUS_KENDALA], true)) {
            $extra['started_at'] = null;
        }

        $paOrder->update(array_merge(['current_status' => $toStatus], $extra));

        // Riwayat status resmi (dipakai halaman Riwayat) -- sebelumnya cuma
        // tercatat di audit_logs, sekarang konsisten dengan alur lain
        // (TaskController, AssignmentController) yang selalu menulis ke sini.
        $paOrder->statusLogs()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $request->user()->id,
            'note' => "[Koreksi Admin] {$data['correction_reason']}",
            'changed_at' => now(),
        ]);

        AuditLogger::log(
            action: 'correct_done_pa_reason',
            entity: 'PaOrder',
            entityId: $paOrder->id,
            detail: $data['correction_reason'],
        );

        return back()->with('status', "PA {$paOrder->pa_number} berhasil dikoreksi.");
    }
}
