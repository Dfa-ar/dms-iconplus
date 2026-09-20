<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\CompletePaRequest;
use App\Http\Requests\Petugas\KendalaPaRequest;
use App\Models\KendalaReason;
use App\Models\PaOrder;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * FR-07: Halaman "Tugas Saya" -- daftar PA dan progres n/20.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaOrder::class);

        $officerId = $request->user()->officer?->id;

        // Akun petugas yang belum tertaut ke baris `officers` -- tanpa guard
        // ini, where('current_officer_id', null) diam-diam berubah jadi
        // whereNull() oleh query builder dan malah menampilkan SEMUA PA yang
        // belum ditugaskan siapa pun ke akun ini.
        if (! $officerId) {
            return view('petugas.dashboard', [
                'tasks' => collect(),
                'progress' => 0,
                'target' => 0,
            ])->with('warning', 'Akun kamu belum terhubung ke data petugas manapun. Hubungi Admin.');
        }

        $tasks = PaOrder::query()
            ->where('current_officer_id', $officerId)
            ->whereDate('assigned_date', now()->toDateString())
            ->orderBy('current_status')
            ->get();

        $doneToday = $tasks->where('current_status', PaOrder::STATUS_DONE)->count();

        return view('petugas.dashboard', [
            'tasks' => $tasks,
            'progress' => $doneToday,
            'target' => $tasks->count(),
        ]);
    }

    public function show(PaOrder $paOrder)
    {
        $this->authorize('view', $paOrder);

        return view('petugas.tugas.show', [
            'paOrder' => $paOrder,
            'kendalaReasons' => KendalaReason::active()->orderBy('label')->get(),
        ]);
    }

    /**
     * FR-08: tombol "Mulai" -- UNASSIGNED tidak mungkin sampai sini karena
     * belum ada current_officer_id; transisi valid hanya ASSIGNED -> ON_PROGRESS.
     */
    public function start(PaOrder $paOrder)
    {
        $this->authorize('start', $paOrder);

        $paOrder->update([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'started_at' => now(),
        ]);

        $paOrder->statusLogs()->create([
            'from_status' => PaOrder::STATUS_ASSIGNED,
            'to_status' => PaOrder::STATUS_ON_PROGRESS,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        return back()->with('status', 'Pekerjaan dimulai.');
    }

    /**
     * FR-08: tombol "Selesai" -- wajib 3 foto + nama penerima (divalidasi
     * di CompletePaRequest, jadi di sini tinggal proses).
     */
    public function complete(CompletePaRequest $request, PaOrder $paOrder)
    {
        $this->authorize('complete', $paOrder);

        $data = $request->validated();
        $fromStatus = $paOrder->current_status;

        $photoFields = [
            'foto_perangkat' => 'perangkat',
            'foto_modem_ont' => 'modem_ont',
            'foto_serah_terima' => 'serah_terima',
        ];

        foreach ($photoFields as $field => $type) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $path = $request->file($field)->store('evidence/' . $paOrder->pa_number, 'public');

            $paOrder->evidences()->create([
                'type' => $type,
                'file_path' => $path,
                'uploaded_by' => $request->user()->id,
                'receiver_name' => $data['receiver_name'],
                'pickup_time' => $data['pickup_time'] ?? now(),
            ]);
        }

        $paOrder->update([
            'current_status' => PaOrder::STATUS_DONE,
            'completed_at' => now(),
            'notes' => $data['notes'] ?? $paOrder->notes,
        ]);

        $paOrder->statusLogs()->create([
            'from_status' => $fromStatus,
            'to_status' => PaOrder::STATUS_DONE,
            'changed_by' => $request->user()->id,
            'changed_at' => now(),
        ]);

        return redirect()
            ->route('petugas.tasks.index')
            ->with('status', "PA {$paOrder->pa_number} ditandai selesai.");
    }

    /**
     * FR-09: tombol "Kendala" -- alasan terstruktur + keterangan wajib.
     */
    public function kendala(KendalaPaRequest $request, PaOrder $paOrder)
    {
        $this->authorize('reportKendala', $paOrder);

        $data = $request->validated();
        $fromStatus = $paOrder->current_status;

        if ($request->hasFile('foto_kendala')) {
            $path = $request->file('foto_kendala')->store('evidence/' . $paOrder->pa_number, 'public');

            $paOrder->evidences()->create([
                'type' => 'kendala',
                'file_path' => $path,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $paOrder->update([
            'current_status' => PaOrder::STATUS_KENDALA,
            'kendala_reason_id' => $data['kendala_reason_id'],
            'notes' => $data['notes'],
        ]);

        $paOrder->statusLogs()->create([
            'from_status' => $fromStatus,
            'to_status' => PaOrder::STATUS_KENDALA,
            'changed_by' => $request->user()->id,
            'kendala_reason_id' => $data['kendala_reason_id'],
            'note' => $data['notes'],
            'changed_at' => now(),
        ]);

        return redirect()
            ->route('petugas.tasks.index')
            ->with('status', "PA {$paOrder->pa_number} dilaporkan kendala.");
    }

    /**
     * Riwayat pekerjaan (Phase 2, disebut di Blueprint struktur menu 8.2).
     */
    public function history(Request $request)
    {
        $this->authorize('viewAny', PaOrder::class);

        $officerId = $request->user()->officer?->id;

        if (! $officerId) {
            return view('petugas.riwayat.index', [
                'history' => collect(),
            ])->with('warning', 'Akun kamu belum terhubung ke data petugas manapun. Hubungi Admin.');
        }

        $history = PaOrder::where('current_officer_id', $officerId)
            ->whereIn('current_status', [PaOrder::STATUS_DONE, PaOrder::STATUS_KENDALA])
            ->orderByDesc('completed_at')
            ->paginate(20);

        return view('petugas.riwayat.index', compact('history'));
    }
}
