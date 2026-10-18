<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\GeocodePaAddress;
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
            ->when($request->filled('geocode_status'), fn ($query) => $query->where('geocode_status', $request->input('geocode_status')))
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', $request->integer('region_id')))
            ->when($request->filled('officer_id'), fn ($query) => $query->where('current_officer_id', $request->integer('officer_id')))
            ->when($request->filled('pa_date'), fn ($query) => $query->whereDate('pa_date', $request->input('pa_date')))
            ->orderByDesc('pa_date')
            ->orderBy('pa_number');

        return view('admin.pa.index', [
            'paOrders' => $query->paginate(25)->withQueryString(),
            'regions' => Region::query()
                ->whereNull('kecamatan')
                ->whereNull('kelurahan')
                ->orderBy('kabupaten_kota')
                ->get()
                ->unique('kabupaten_kota')
                ->values(),
            'officers' => Officer::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => [
                PaOrder::STATUS_UNASSIGNED,
                PaOrder::STATUS_ASSIGNED,
                PaOrder::STATUS_ON_PROGRESS,
                PaOrder::STATUS_DONE,
                PaOrder::STATUS_KENDALA,
            ],
            'geocodeStatuses' => [
                PaOrder::GEOCODE_STATUS_PENDING => 'Menunggu geocoding',
                PaOrder::GEOCODE_STATUS_OK => 'Lokasi ditemukan',
                PaOrder::GEOCODE_STATUS_FAILED => 'Perlu tindakan',
            ],
        ]);
    }

    public function show(PaOrder $paOrder)
    {
        $this->authorize('view', $paOrder);

        $paOrder->load(['region', 'currentOfficer', 'kendalaReason', 'evidences', 'statusLogs.changedBy']);

        return view('admin.pa.show', compact('paOrder'));
    }

    public function updateCoordinates(Request $request, PaOrder $paOrder)
    {
        $this->authorize('update', $paOrder);

        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $paOrder->update([
            ...$coordinates,
            'geocode_status' => PaOrder::GEOCODE_STATUS_OK,
            'geocode_error' => null,
        ]);

        AuditLogger::log(
            'pa_coordinates_updated',
            'PaOrder',
            $paOrder->id,
            sprintf('Koordinat PA %s diisi manual: %s, %s.', $paOrder->pa_number, $coordinates['latitude'], $coordinates['longitude'])
        );

        return back()->with('status', 'Koordinat pelanggan berhasil disimpan.');
    }

    public function queueGeocoding(Request $request, PaOrder $paOrder)
    {
        $this->authorize('update', $paOrder);

        if (is_numeric($paOrder->latitude) && is_numeric($paOrder->longitude)) {
            return back()->with('status', 'Koordinat PA sudah tersedia; ubah koordinat manual bila perlu diperbaiki.');
        }

        $paOrder->update([
            'geocode_status' => PaOrder::GEOCODE_STATUS_PENDING,
            'geocode_error' => null,
        ]);

        GeocodePaAddress::dispatch($paOrder->id);

        return back()->with('status', 'Geocoding PA masuk antrean. Muat ulang halaman setelah worker selesai.');
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

        $paOrder->correctStatus(array_merge(['current_status' => $toStatus], $extra));

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
