<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('generateAssignment');

        $regions = Region::query()
            ->whereNull('kecamatan')
            ->whereNull('kelurahan')
            ->orderBy('kabupaten_kota')
            ->get();
        $selectedRegion = $request->filled('region_id')
            ? Region::find($request->integer('region_id'))
            : null;
        $target = max(1, min($request->integer('target_per_officer') ?: 20, 100));
        $officers = collect();
        $candidates = collect();

        if ($selectedRegion) {
            $officers = Officer::query()
                ->whereHas('region', fn ($query) => $query->where('kabupaten_kota', $selectedRegion->kabupaten_kota))
                ->where('is_active', true)
                ->withCount(['currentPaOrders as carry_over_count' => function ($query) {
                    $query->whereIn('current_status', [PaOrder::STATUS_ASSIGNED, PaOrder::STATUS_ON_PROGRESS]);
                }])
                ->orderBy('name')
                ->get();

            $candidates = PaOrder::with(['region'])
                ->whereHas('region', fn ($query) => $query->where('kabupaten_kota', $selectedRegion->kabupaten_kota))
                ->where('current_status', PaOrder::STATUS_UNASSIGNED)
                ->orderBy('pa_date')
                ->limit(100)
                ->get();
        }

        return view('admin.assignment.index', compact(
            'regions', 'selectedRegion', 'target', 'officers', 'candidates'
        ));
    }

    /**
     * FR-05 + Blueprint 11.1: Generate assignment harian.
     *
     * CATATAN / ASUMSI: skema `regions` menyimpan baris granular
     * (kabupaten_kota + kecamatan + kelurahan sekaligus), sedangkan
     * `officers.region_id` diasumsikan menunjuk ke baris "level
     * kabupaten/kota" (kecamatan & kelurahan null) yang mewakili area
     * kerja petugas secara umum. Supaya petugas di baris kab/kota itu
     * bisa menerima PA yang region_id-nya adalah baris kecamatan/kelurahan
     * SPESIFIK, pencocokan wilayah di bawah ini dilakukan lewat kolom
     * kabupaten_kota yang sama -- bukan lewat region_id yang identik.
     * Kalau ternyata skema wilayah di project kamu beda, sesuaikan method
     * resolvePaCandidates() di bawah.
     */
    public function generate(GenerateAssignmentRequest $request)
    {
        $data = $request->validated();
        $baseRegion = Region::findOrFail($data['region_id']);
        $target = $request->targetPerOfficer();

        $result = DB::transaction(fn () => $this->runGenerate($baseRegion, $target));

        if ($result['officers_count'] === 0) {
            return back()->withErrors([
                'region_id' => 'Tidak ada petugas aktif terdaftar di wilayah ini.',
            ]);
        }

        return back()->with(
            'status',
            "{$result['assigned']} PA berhasil dibagikan ke {$result['officers_count']} petugas aktif di {$baseRegion->kabupaten_kota}."
        );
    }

    private function runGenerate(Region $baseRegion, int $target): array
    {
        // Disamakan dengan resolvePaCandidates(): dicocokkan lewat teks
        // kabupaten_kota, bukan region_id yang harus identik persis --
        // supaya konsisten dengan asumsi yang sudah ditulis di atas.
        $officers = Officer::whereHas('region', function ($q) use ($baseRegion) {
            $q->where('kabupaten_kota', $baseRegion->kabupaten_kota);
        })
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($officers->isEmpty()) {
            return ['assigned' => 0, 'officers_count' => 0];
        }

        // Langkah 2-3: carry-over dan kuota baru per petugas
        $carryOverCounts = PaOrder::whereIn('current_officer_id', $officers->pluck('id'))
            ->whereIn('current_status', [PaOrder::STATUS_ASSIGNED, PaOrder::STATUS_ON_PROGRESS])
            ->selectRaw('current_officer_id, COUNT(*) as total')
            ->groupBy('current_officer_id')
            ->pluck('total', 'current_officer_id');

        $quotas = [];
        foreach ($officers as $officer) {
            $carryOver = (int) ($carryOverCounts[$officer->id] ?? 0);
            $quotas[$officer->id] = max($target - $carryOver, 0);
        }

        // Langkah 4: kandidat PA UNASSIGNED di wilayah yang sama, aging
        // tertinggi dulu (pa_date paling lama = paling lama menunggu),
        // dikelompokkan per kecamatan+kelurahan untuk efisiensi rute.
        $candidates = $this->resolvePaCandidates($baseRegion);

        $groups = $candidates
            ->groupBy(fn (PaOrder $pa) => ($pa->region?->kecamatan ?? '-') . '|' . ($pa->region?->kelurahan ?? '-'))
            ->map(fn ($group) => $group->values());

        $groupKeys = $groups->keys()->values();

        if ($groupKeys->isEmpty()) {
            return ['assigned' => 0, 'officers_count' => $officers->count()];
        }

        $pointers = $groupKeys->mapWithKeys(fn ($key) => [$key => 0]);
        $officerIds = $officers->pluck('id')->values();

        $totalAssigned = 0;
        $officerIndex = 0;
        $groupIndex = 0;
        $remainingGroups = $groupKeys->count();

        // Langkah 5: round-robin petugas x kelompok wilayah sampai kuota
        // habis atau PA UNASSIGNED habis.
        while (array_sum($quotas) > 0 && $remainingGroups > 0) {
            $officerId = $officerIds[$officerIndex % $officerIds->count()];

            if ($quotas[$officerId] > 0) {
                $groupKey = $groupKeys[$groupIndex % $groupKeys->count()];
                $pointer = $pointers[$groupKey];
                $group = $groups[$groupKey];

                if ($pointer < $group->count()) {
                    $pa = $group[$pointer];
                    $pointers[$groupKey] = $pointer + 1;

                    $this->assignOnePa($pa, $officerId);

                    $quotas[$officerId]--;
                    $totalAssigned++;

                    if ($pointers[$groupKey] >= $group->count()) {
                        $remainingGroups--;
                    }
                }
            }

            $officerIndex++;
            $groupIndex++;

            // safety valve: hindari infinite loop kalau semua grup terpakai
            // tapi sisa quota belum 0 karena grup lebih sedikit dari petugas
            if ($officerIndex > 100000) {
                break;
            }
        }

        return ['assigned' => $totalAssigned, 'officers_count' => $officers->count()];
    }

    private function resolvePaCandidates(Region $baseRegion)
    {
        return PaOrder::query()
            ->whereHas('region', function ($q) use ($baseRegion) {
                $q->where('kabupaten_kota', $baseRegion->kabupaten_kota);
            })
            ->where('current_status', PaOrder::STATUS_UNASSIGNED)
            ->orderBy('pa_date') // aging tertinggi dulu = pa_date paling lama
            ->with('region')
            ->get();
    }

    private function assignOnePa(PaOrder $pa, int $officerId): void
    {
        // Langkah 6: simpan ke assignments, ubah status, catat status_logs
        $pa->update([
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $officerId,
            'assigned_date' => now()->toDateString(),
        ]);

        Assignment::create([
            'pa_id' => $pa->id,
            'officer_id' => $officerId,
            'assign_date' => now()->toDateString(),
            'assigned_by' => auth()->id(),
            'source' => 'auto',
        ]);

        $pa->statusLogs()->create([
            'from_status' => PaOrder::STATUS_UNASSIGNED,
            'to_status' => PaOrder::STATUS_ASSIGNED,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    /**
     * FR-06: ubah assignment manual (pindah petugas / lepas dari petugas).
     */
    public function reassign(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $data = $request->validate([
            'officer_id' => ['nullable', 'exists:officers,id'],
        ]);

        $oldOfficerId = $assignment->officer_id;
        $paOrder = $assignment->paOrder;

        if (blank($data['officer_id']) && $paOrder->current_status !== PaOrder::STATUS_ASSIGNED) {
            return back()->withErrors([
                'assignment' => 'Hanya tugas yang belum dimulai yang dapat ditarik kembali.',
            ]);
        }

        if (blank($data['officer_id'])) {
            $paOrder->update([
                'current_officer_id' => null,
                'current_status' => PaOrder::STATUS_UNASSIGNED,
                'assigned_date' => null,
            ]);
            $assignment->update(['released_at' => now()]);
            $paOrder->statusLogs()->create([
                'from_status' => PaOrder::STATUS_ASSIGNED,
                'to_status' => PaOrder::STATUS_UNASSIGNED,
                'changed_by' => auth()->id(),
                'note' => 'Assignment ditarik kembali oleh Admin.',
                'changed_at' => now(),
            ]);
        } else {
            $paOrder->update(['current_officer_id' => $data['officer_id']]);
            $assignment->update(['officer_id' => $data['officer_id']]);
        }

        // FR-06 wajib tercatat -- reassignment tidak mengubah current_status
        // jadi PaOrderObserver TIDAK otomatis menangkap perubahan ini.
        \App\Services\AuditLogger::log(
            action: 'reassign_manual',
            entity: 'PaOrder',
            entityId: $paOrder->id,
            detail: "Petugas #{$oldOfficerId} \u2192 #".($data['officer_id'] ?? 'NULL')." (assignment #{$assignment->id})"
        );

        return back()->with('status', 'Assignment berhasil diubah.');
    }
}
