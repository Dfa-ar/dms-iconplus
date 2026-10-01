<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\CompletePaRequest;
use App\Http\Requests\Petugas\KendalaPaRequest;
use App\Models\Evidence;
use App\Models\FatPoint;
use App\Models\KendalaReason;
use App\Models\PaOrder;
use App\Models\Splitter;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $doneStatuses = [
            PaOrder::STATUS_DONE,
            PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            PaOrder::QC_STATUS_PASSED,
            PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
            PaOrder::BLUEPRINT_STATUS_PAID,
        ];
        $doneToday = $tasks->whereIn('current_status', $doneStatuses)->count();

        return view('petugas.dashboard', [
            'tasks' => $tasks,
            'progress' => $doneToday,
            'target' => $tasks->count(),
        ]);
    }

    public function show(PaOrder $paOrder)
    {
        $this->authorize('view', $paOrder);
        $paOrder->load('evidences');

        $fatPoints = FatPoint::query()
            ->where('is_active', true)
            ->where('region_id', $paOrder->region_id)
            ->with(['splitters' => fn ($query) => $query->where('is_active', true)->orderBy('kode_splitter')])
            ->orderBy('kode_fat')
            ->get();

        return view('petugas.tugas.show', [
            'paOrder' => $paOrder,
            'kendalaReasons' => KendalaReason::active()->orderBy('label')->get(),
            'fatPoints' => $fatPoints,
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

        AuditLogger::log('task_started', 'PaOrder', $paOrder->id, "PA {$paOrder->pa_number} dimulai oleh petugas.");

        return back()->with('status', 'Pekerjaan dimulai.');
    }

    /** Simpan hanya langkah Close ICRM berikutnya setelah validasi server. */
    public function closeIcrm(Request $request, PaOrder $paOrder)
    {
        $this->authorize('complete', $paOrder);

        $validatedStep = $request->validate([
            'step_number' => ['required', 'integer', 'between:1,6'],
        ]);
        $stepNumber = (int) $validatedStep['step_number'];
        $savedStep = $this->savedCloseIcrmStep($paOrder);

        if ($stepNumber !== $savedStep + 1) {
            return back()->withErrors([
                'step_number' => 'Selesaikan checklist secara berurutan sebelum melanjutkan.',
            ])->withInput();
        }

        if ($stepNumber === 4 && blank($paOrder->id_pln)) {
            return back()->withErrors([
                'id_pln_confirmed' => 'ID PLN belum tersedia pada data PA. Hubungi Admin untuk memperbaiki data upload.',
            ])->withInput();
        }

        $image = ['required', 'image', 'max:4096'];
        $rules = match ($stepNumber) {
            1 => ['foto_k3_awal' => $image],
            2 => [
                'foto_ont_depan' => $image,
                'foto_ont_belakang_sn' => $image,
                'serial_number_ont' => ['nullable', 'string', 'max:120'],
            ],
            3 => [
                'kabel_panjang_meter' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'foto_fat_terdekat' => $image,
                'fat_point_id' => ['required', 'integer', Rule::exists('fat_points', 'id')->where('is_active', true)],
                'splitter_id' => ['required', 'integer', Rule::exists('splitters', 'id')->where('is_active', true)],
                'port_number' => ['required', 'integer', 'min:1'],
            ],
            4 => [
                'id_pln_confirmed' => ['required', 'accepted'],
                'kwh_status' => ['required', 'in:ADA,TIDAK_DITEMUKAN'],
                'foto_kwh_meter' => ['nullable', 'required_if:kwh_status,ADA', 'image', 'max:4096'],
                'kwh_note' => ['required', 'string', 'max:255'],
            ],
            5 => ['foto_k3_akhir' => $image],
            6 => [
                'ba_pengambilan_perangkat' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
                'receiver_name' => ['required', 'string', 'max:150'],
            ],
        };

        $data = $request->validate($rules, [
            'required' => 'Bagian ini wajib diisi sebelum melanjutkan.',
            'accepted' => 'Konfirmasi harus dicentang sebelum melanjutkan.',
            'image' => 'Bukti harus berupa gambar.',
            'file' => 'BA Pengambilan harus berupa PDF atau gambar.',
            'max' => 'Ukuran file melebihi batas yang diizinkan.',
            'sn_ont_readable.in' => 'Pilih apakah SN pada foto terbaca dengan jelas.',
            'kwh_status.in' => 'Pilih status KWH Meter.',
            'port_number.min' => 'Port minimal adalah 1.',
        ]);

        if ($stepNumber === 3) {
            $fatPoint = FatPoint::query()
                ->whereKey($data['fat_point_id'])
                ->where('is_active', true)
                ->with(['splitters' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['paOrders' => fn ($paQuery) => $paQuery
                        ->where('pa_orders.id', '!=', $paOrder->id)
                        ->select('pa_orders.id', 'pa_orders.splitter_id', 'pa_orders.port_number')])
                    ->orderBy('kode_splitter')])
                ->first();
            $splitter = Splitter::query()
                ->whereKey($data['splitter_id'])
                ->where('is_active', true)
                ->where('fat_point_id', $data['fat_point_id'])
                ->first();

            if (! $fatPoint || ! $splitter) {
                return back()->withErrors([
                    'splitter_id' => 'Pilih FAT dan splitter aktif yang sesuai dengan wilayah PA.',
                ])->withInput();
            }

            if ($data['port_number'] > $splitter->capacity_port) {
                return back()->withErrors([
                    'port_number' => "Port splitter ini hanya tersedia sampai nomor {$splitter->capacity_port}.",
                ])->withInput();
            }

            $portInUse = PaOrder::query()
                ->where('id', '!=', $paOrder->id)
                ->where('splitter_id', $splitter->id)
                ->where('port_number', $data['port_number'])
                ->exists();

            if ($portInUse) {
                return back()->withErrors([
                    'port_number' => 'Port yang dipilih sudah digunakan PA lain.',
                ])->withInput();
            }
        }

        foreach ($this->stepEvidence($stepNumber, $data) as $field => $type) {
            $this->storeEvidence($request, $paOrder, $field, $type);
        }

        $stepLabel = "STEP_{$stepNumber}";
        $attributes = ['close_icrm_step' => $stepLabel];

        if ($stepNumber === 2) {
            $attributes['serial_number_ont'] = filled($data['serial_number_ont'] ?? null)
                ? trim($data['serial_number_ont'])
                : null;
        } elseif ($stepNumber === 3) {
            $attributes['kabel_panjang_meter'] = $data['kabel_panjang_meter'];
            $attributes['fat_point_id'] = $data['fat_point_id'];
            $attributes['splitter_id'] = $data['splitter_id'];
            $attributes['port_number'] = $data['port_number'];
        } elseif ($stepNumber === 4) {
            $attributes['id_pln_confirmed'] = true;
            $attributes['kwh_status'] = $data['kwh_status'];
            $attributes['kwh_note'] = trim($data['kwh_note']);
        } elseif ($stepNumber === 6) {
            $attributes['current_status'] = PaOrder::BLUEPRINT_STATUS_PENDING_QC;
            $attributes['completed_at'] = now();
            $attributes['qc_status'] = PaOrder::QC_STATUS_PENDING;
        }

        $fromStatus = $paOrder->current_status;
        $paOrder->update($attributes);

        if ($stepNumber === 6) {
            $paOrder->statusLogs()->create([
                'from_status' => $fromStatus,
                'to_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
                'changed_by' => $request->user()->id,
                'note' => 'Checklist Close ICRM lengkap; dokumen BA Pengambilan telah diunggah dan menunggu QC.',
                'changed_at' => now(),
            ]);
        }

        AuditLogger::log(
            'close_icrm_step_saved',
            'PaOrder',
            $paOrder->id,
            "Langkah {$stepNumber} Close ICRM tersimpan untuk PA {$paOrder->pa_number}."
        );

        if ($stepNumber === 6) {
            return redirect()->route('petugas.tasks.index')->with('status', "PA {$paOrder->pa_number} selesai dan masuk antrean QC.");
        }

        return redirect()->route('petugas.tasks.show', $paOrder)->with('status', "Langkah {$stepNumber} tersimpan. Lanjutkan ke langkah " . ($stepNumber + 1) . '.');
    }

    public function complete(CompletePaRequest $request, PaOrder $paOrder)
    {
        $this->authorize('complete', $paOrder);
        if ($this->savedCloseIcrmStep($paOrder) !== 5) {
            return back()->withErrors([
                'close_icrm_step' => 'Selesaikan langkah 1 sampai 5 melalui wizard sebelum mengunggah BA Pengambilan.',
            ]);
        }

        $request->merge(['step_number' => 6]);

        return $this->closeIcrm($request, $paOrder);
    }

    private function savedCloseIcrmStep(PaOrder $paOrder): int
    {
        return (int) str_replace('STEP_', '', (string) $paOrder->close_icrm_step);
    }

    /** @return array<string, string> */
    private function stepEvidence(int $stepNumber, array $data): array
    {
        return match ($stepNumber) {
            1 => ['foto_k3_awal' => 'k3_awal'],
            2 => [
                'foto_ont_depan' => 'ont_depan',
                'foto_ont_belakang_sn' => 'ont_belakang_sn',
            ],
            3 => ['foto_fat_terdekat' => 'fat_terdekat'],
            4 => ($data['kwh_status'] ?? null) === 'ADA' ? ['foto_kwh_meter' => 'kwh_meter'] : [],
            5 => ['foto_k3_akhir' => 'k3_akhir'],
            6 => ['ba_pengambilan_perangkat' => 'ba_pengambilan_perangkat'],
        };
    }

    private function storeEvidence(Request $request, PaOrder $paOrder, string $field, string $type): void
    {
        $path = $request->file($field)->store('evidence/' . $paOrder->pa_number, 'public');
        $evidence = [
            'type' => $type,
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ];

        if ($type === 'ba_pengambilan_perangkat') {
            $evidence['receiver_name'] = $request->input('receiver_name');
            $evidence['pickup_time'] = now();
        }

        $paOrder->evidences()->create($evidence);
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

        AuditLogger::log(
            'task_kendala_reported',
            'PaOrder',
            $paOrder->id,
            "PA {$paOrder->pa_number} dilaporkan kendala: {$data['notes']}"
        );

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
            ->whereIn('current_status', [
                PaOrder::STATUS_DONE,
                PaOrder::STATUS_KENDALA,
                PaOrder::BLUEPRINT_STATUS_PENDING_QC,
                PaOrder::QC_STATUS_PASSED,
                PaOrder::QC_STATUS_REJECTED,
                PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
                PaOrder::BLUEPRINT_STATUS_PAID,
            ])
            ->orderByDesc('completed_at')
            ->paginate(20);

        return view('petugas.riwayat.index', compact('history'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $officer = $user->officer()->with(['region', 'kantorPerwakilan'])->first();

        return view('petugas.profile.index', compact('user', 'officer'));
    }
}
