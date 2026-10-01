<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\PaymentBatch;
use App\Models\Region;
use App\Models\Assignment;
use App\Models\BastDocument;
use App\Models\KendalaReason;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportsController extends Controller
{
    private const REPORTS = [
        'pa' => 'Data PA per periode',
        'officers' => 'Performa petugas',
        'kp' => 'Rekap Kantor Perwakilan',
        'kendala' => 'Rekap kendala',
        'bast' => 'Rekap BAST',
        'payments' => 'Rekap pembayaran',
    ];

    public function index()
    {
        $this->authorize('exportReport');

        return view('admin.reports.index', [
            'reports' => self::REPORTS,
            'kantorPerwakilan' => KantorPerwakilan::orderBy('nama')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('exportReport');
        $filters = $request->validate([
            'report_type' => ['required', 'in:' . implode(',', array_keys(self::REPORTS))],
            'kantor_perwakilan_id' => ['nullable', 'integer', 'exists:kantor_perwakilan,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        [$headers, $rows] = $this->reportRows($filters);
        AuditLogger::log(
            'report_exported',
            'Report',
            null,
            'Laporan ' . self::REPORTS[$filters['report_type']] . ' diekspor ke XLSX. Filter: ' . json_encode($filters)
        );
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(self::REPORTS[$filters['report_type']], 0, 31));
        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', $sheet->getHighestDataColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'laporan-' . $filters['report_type'] . '-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function reportRows(array $filters): array
    {
        return match ($filters['report_type']) {
            'pa' => $this->paRows($filters),
            'officers' => $this->officerRows($filters),
            'kp' => $this->kpRows($filters),
            'kendala' => $this->kendalaRows($filters),
            'bast' => $this->bastRows($filters),
            'payments' => $this->paymentRows($filters),
        };
    }

    private function paRows(array $filters): array
    {
        $orders = $this->applyKpFilter(PaOrder::with(['region', 'currentOfficer', 'bastDocument']), $filters)
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_date', '<=', $date))
            ->orderBy('pa_number')
            ->get();

        $rows = $orders->map(fn (PaOrder $pa) => [
            $pa->pa_number,
            $pa->customer_id,
            $pa->customer_name,
            $pa->region?->kabupaten_kota,
            $pa->currentOfficer?->name,
            $pa->current_status,
            $pa->qc_status,
            $pa->payment_status,
            $pa->bastDocument?->nomor_bast,
            $pa->pa_date ? Carbon::parse($pa->pa_date)->format('Y-m-d') : null,
        ])->all();

        return [['No PA', 'ID Pelanggan', 'Nama Pelanggan', 'Wilayah', 'Petugas', 'Status', 'Status QC', 'Pembayaran', 'Nomor BAST', 'Tanggal PA'], $rows];
    }

    private function officerRows(array $filters): array
    {
        $officers = Officer::query()
            ->with(['region', 'kantorPerwakilan'])
            ->withCount(['assignments as assignments_in_period_count' => fn (Builder $query) => $query
                ->when($filters['from_date'] ?? null, fn (Builder $assignments, $date) => $assignments->whereDate('assign_date', '>=', $date))
                ->when($filters['to_date'] ?? null, fn (Builder $assignments, $date) => $assignments->whereDate('assign_date', '<=', $date))])
            ->when($filters['kantor_perwakilan_id'] ?? null, fn (Builder $query, $officeId) => $query
                ->where(fn (Builder $query) => $query->where('kantor_perwakilan_id', $officeId)
                    ->orWhereHas('region', fn (Builder $regionQuery) => $regionQuery->where('kantor_perwakilan_id', $officeId))))
            ->orderBy('name')
            ->get();

        $rows = $officers->map(fn (Officer $officer) => [
            $officer->employee_code,
            $officer->name,
            $officer->kantorPerwakilan?->nama ?? $officer->region?->kantorPerwakilan?->nama,
            $officer->region?->kabupaten_kota,
            $officer->is_active ? 'Aktif' : 'Nonaktif',
            $officer->assignments_in_period_count,
            $officer->currentPaOrders()->where('current_status', PaOrder::STATUS_ASSIGNED)->count(),
            $officer->currentPaOrders()->where('current_status', PaOrder::STATUS_ON_PROGRESS)->count(),
        ])->all();

        return [['Kode Petugas', 'Nama', 'KP', 'Wilayah', 'Status', 'Assignment Periode', 'Assigned Saat Ini', 'On Progress Saat Ini'], $rows];
    }

    private function kpRows(array $filters): array
    {
        $offices = KantorPerwakilan::query()
            ->withCount(['regions', 'officers'])
            ->when($filters['kantor_perwakilan_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->orderBy('kode')
            ->get();

        $rows = $offices->map(function (KantorPerwakilan $office) use ($filters) {
            $regionIds = $office->regions()->pluck('regions.id');
            $orders = PaOrder::query()->where(fn (Builder $query) => $query
                ->where('kantor_perwakilan_id', $office->id)
                ->when($regionIds->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('region_id', $regionIds)))
                ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_date', '>=', $date))
                ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_date', '<=', $date));

            return [
                $office->kode,
                $office->nama,
                $office->regions_count,
                $office->officers_count,
                (clone $orders)->count(),
                (clone $orders)->whereIn('current_status', [PaOrder::STATUS_DONE, PaOrder::BLUEPRINT_STATUS_PENDING_QC, PaOrder::QC_STATUS_PASSED, PaOrder::BLUEPRINT_STATUS_BAST_ISSUED, PaOrder::BLUEPRINT_STATUS_PAID])->count(),
                (clone $orders)->where('current_status', PaOrder::BLUEPRINT_STATUS_PENDING_QC)->count(),
                (clone $orders)->where('current_status', PaOrder::BLUEPRINT_STATUS_BAST_ISSUED)->count(),
                (clone $orders)->where('current_status', PaOrder::BLUEPRINT_STATUS_PAID)->count(),
            ];
        })->all();

        return [['Kode KP', 'Kantor Perwakilan', 'Wilayah', 'Petugas', 'Total PA', 'Selesai Lapangan', 'QC Pending', 'BAST Issued', 'Lunas'], $rows];
    }

    private function kendalaRows(array $filters): array
    {
        $orders = $this->applyKpFilter(PaOrder::with(['region', 'currentOfficer', 'kendalaReason']), $filters)
            ->where('current_status', PaOrder::STATUS_KENDALA)
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('updated_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('updated_at', '<=', $date))
            ->orderByDesc('updated_at')
            ->get();

        $rows = $orders->map(fn (PaOrder $pa) => [
            $pa->pa_number,
            $pa->customer_id,
            $pa->customer_name,
            $pa->region?->kabupaten_kota,
            $pa->currentOfficer?->name,
            $pa->kendalaReason?->label,
            $pa->notes,
            $pa->updated_at?->format('Y-m-d H:i:s'),
        ])->all();

        return [['No PA', 'ID Pelanggan', 'Nama', 'Wilayah', 'Petugas', 'Alasan', 'Catatan', 'Waktu Update'], $rows];
    }

    private function bastRows(array $filters): array
    {
        $documents = BastDocument::with(['kantorPerwakilan', 'region', 'creator'])
            ->withCount(['items', 'archivedItems'])
            ->when($filters['kantor_perwakilan_id'] ?? null, fn (Builder $query, $officeId) => $query->where('kantor_perwakilan_id', $officeId))
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('tanggal', '<=', $date))
            ->orderBy('tanggal')
            ->get();

        $rows = $documents->map(fn (BastDocument $document) => [
            $document->nomor_bast,
            $document->kantorPerwakilan?->kode,
            $document->kantorPerwakilan?->nama ?? $document->region?->kabupaten_kota,
            $document->tanggal ? Carbon::parse($document->tanggal)->format('Y-m-d') : null,
            $document->status,
            $document->items_count + $document->archived_items_count,
            $document->void_reason,
            $document->creator?->name,
        ])->all();

        return [['Nomor BAST', 'Kode KP', 'KP', 'Tanggal', 'Status', 'Jumlah PA', 'Alasan Void', 'Dibuat Oleh'], $rows];
    }

    private function paymentRows(array $filters): array
    {
        $batches = PaymentBatch::with(['creator', 'items.paOrder.region'])
            ->withSum('items', 'amount')
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('payment_date', '<=', $date))
            ->when($filters['kantor_perwakilan_id'] ?? null, fn (Builder $query, $officeId) => $query->whereHas('items.paOrder', fn (Builder $paQuery) => $this->applyKpFilter($paQuery, $filters)))
            ->orderBy('payment_date')
            ->get();

        $rows = $batches->flatMap(function (PaymentBatch $batch) use ($filters) {
            return $batch->items
                ->filter(fn ($item) => ! ($filters['kantor_perwakilan_id'] ?? null)
                    || (int) $item->paOrder?->kantor_perwakilan_id === (int) $filters['kantor_perwakilan_id']
                    || (int) $item->paOrder?->region?->kantor_perwakilan_id === (int) $filters['kantor_perwakilan_id'])
                ->map(fn ($item) => [
                    $batch->payment_date ? Carbon::parse($batch->payment_date)->format('Y-m-d') : null,
                    $batch->id,
                    $batch->method,
                    $batch->reference_no,
                    $item->paOrder?->pa_number,
                    $item->paOrder?->region?->kantorPerwakilan?->kode,
                    (float) $item->amount,
                    (float) $batch->items_sum_amount,
                    $batch->created_by,
                    $batch->notes,
                ]);
        })->values()->all();

        return [['Tanggal', 'Batch', 'Metode', 'Referensi', 'No PA', 'Kode KP', 'Nominal PA', 'Total Batch', 'Dibuat Oleh', 'Catatan'], $rows];
    }

    private function applyKpFilter(Builder $query, array $filters): Builder
    {
        if (! ($filters['kantor_perwakilan_id'] ?? null)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($filters) {
            $query->where('pa_orders.kantor_perwakilan_id', $filters['kantor_perwakilan_id'])
                ->orWhereHas('region', fn (Builder $regionQuery) => $regionQuery->where('kantor_perwakilan_id', $filters['kantor_perwakilan_id']));
        });
    }
}