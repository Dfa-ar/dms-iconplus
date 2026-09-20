<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KendalaReason;
use App\Models\Assignment;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\SlaSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class DashboardController extends Controller
{
    public function index()
    {
        $this->authorize('viewDashboard');

        $statusCounts = PaOrder::selectRaw('current_status, COUNT(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $sla = SlaSetting::first() ?? new SlaSetting(['sla_days' => 14]);

        $regions = PaOrder::query()
            ->join('regions', 'regions.id', '=', 'pa_orders.region_id')
            ->selectRaw(
                "COALESCE(regions.parent_group, regions.kabupaten_kota) as wilayah, COUNT(*) as total, SUM(CASE WHEN current_status = ? THEN 1 ELSE 0 END) as done",
                [PaOrder::STATUS_DONE]
            )
            ->groupBy('wilayah')
            ->get()
            ->map(fn ($row) => [
                'wilayah' => $row->wilayah,
                'total' => (int) $row->total,
                'done' => (int) $row->done,
                'percentage' => $row->total > 0 ? round($row->done / $row->total * 100, 1) : 0,
            ]);

        $topKendala = PaOrder::query()
            ->join('kendala_reasons', 'kendala_reasons.id', '=', 'pa_orders.kendala_reason_id')
            ->where('current_status', PaOrder::STATUS_KENDALA)
            ->whereDate('pa_orders.updated_at', now()->toDateString())
            ->selectRaw('kendala_reasons.label, COUNT(*) as total')
            ->groupBy('kendala_reasons.label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $escalations = PaOrder::with(['region', 'currentOfficer'])
            ->whereNotIn('current_status', [PaOrder::STATUS_DONE])
            ->get()
            ->sortByDesc('aging')
            ->take(20)
            ->map(fn (PaOrder $pa) => [
                'pa' => $pa,
                'aging' => $pa->aging,
                'priority' => $this->priorityFor($pa->aging, $sla),
            ]);

        $overSla = PaOrder::whereNotIn('current_status', [PaOrder::STATUS_DONE])
            ->whereDate('pa_date', '<=', now()->subDays($sla->sla_days))
            ->count();

        $total = array_sum($statusCounts->toArray());
        $statusPercentages = collect([
            PaOrder::STATUS_UNASSIGNED => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
            PaOrder::STATUS_ASSIGNED => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
            PaOrder::STATUS_ON_PROGRESS => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            PaOrder::STATUS_DONE => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
            PaOrder::STATUS_KENDALA => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
        ])->map(fn ($count) => $total > 0 ? round($count / $total * 100, 1) : 0);

        $today = now()->toDateString();
        $todayStatusCounts = PaOrder::query()
            ->whereDate('assigned_date', $today)
            ->selectRaw('current_status, COUNT(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');
        $todaySummary = [
            'assigned' => Assignment::whereDate('assign_date', $today)->count(),
            'done' => PaOrder::whereDate('completed_at', $today)->where('current_status', PaOrder::STATUS_DONE)->count(),
            'progress' => $todayStatusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            'kendala' => $todayStatusCounts[PaOrder::STATUS_KENDALA] ?? 0,
        ];

        $officerProgress = Officer::query()
            ->with('region')
            ->where('is_active', true)
            ->withCount([
                'assignments as assigned_today_count' => fn ($query) => $query->whereDate('assign_date', $today),
                'currentPaOrders as assigned_count' => fn ($query) => $query->where('current_status', PaOrder::STATUS_ASSIGNED)->whereDate('assigned_date', $today),
                'currentPaOrders as progress_count' => fn ($query) => $query->where('current_status', PaOrder::STATUS_ON_PROGRESS)->whereDate('assigned_date', $today),
                'currentPaOrders as kendala_count' => fn ($query) => $query->where('current_status', PaOrder::STATUS_KENDALA)->whereDate('assigned_date', $today),
                'currentPaOrders as done_count' => fn ($query) => $query->where('current_status', PaOrder::STATUS_DONE)->whereDate('completed_at', $today),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.dashboard.index', [
            'total' => $total,
            'done' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
            'onProgress' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            'kendala' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
            'overSla' => $overSla,
            'statusPercentages' => $statusPercentages,
            'statusChart' => [
                'labels' => ['Belum Ditugaskan', 'Ditugaskan', 'Diproses', 'Selesai', 'Kendala'],
                'values' => [
                    $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                    $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                    $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                    $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                    $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                ],
            ],
            'regionChart' => [
                'labels' => $regions->pluck('wilayah')->values(),
                'values' => $regions->pluck('percentage')->values(),
            ],
            'todaySummary' => $todaySummary,
            'officerProgress' => $officerProgress,
            'regions' => $regions,
            'topKendala' => $topKendala,
            'escalations' => $escalations,
        ]);
    }

    private function priorityFor(int $aging, SlaSetting $sla): array
    {
        return match (true) {
            $aging <= $sla->aging_green_max => ['level' => 'green', 'label' => 'Normal'],
            $aging <= $sla->aging_yellow_max => ['level' => 'yellow', 'label' => 'Perlu perhatian'],
            $aging <= $sla->aging_orange_max => ['level' => 'orange', 'label' => 'Tinggi'],
            default => ['level' => 'red', 'label' => 'Kritis (Over SLA)'],
        };
    }

    /**
     * FR-11: progress per wilayah, dikelompokkan per `parent_group`
     * (mis. "Bandung", "Cirebon", "Tasikmalaya") sesuai tampilan mockup.
     */
    public function regions()
    {
        $this->authorize('viewDashboard');

        $rows = PaOrder::query()
            ->join('regions', 'regions.id', '=', 'pa_orders.region_id')
            ->selectRaw("
                COALESCE(regions.parent_group, regions.kabupaten_kota) as wilayah,
                COUNT(*) as total,
                SUM(CASE WHEN current_status = ? THEN 1 ELSE 0 END) as done
            ", [PaOrder::STATUS_DONE])
            ->groupBy('wilayah')
            ->get()
            ->map(fn ($row) => [
                'wilayah' => $row->wilayah,
                'total' => (int) $row->total,
                'done' => (int) $row->done,
                'percentage' => $row->total > 0 ? round($row->done / $row->total * 100, 1) : 0,
            ]);

        return view('admin.dashboard.regions', ['regions' => $rows]);
    }

    /**
     * FR-11: top kendala hari ini, diurutkan dari yang paling sering.
     */
    public function kendala()
    {
        $this->authorize('viewDashboard');

        $top = PaOrder::query()
            ->join('kendala_reasons', 'kendala_reasons.id', '=', 'pa_orders.kendala_reason_id')
            ->where('current_status', PaOrder::STATUS_KENDALA)
            ->whereDate('pa_orders.updated_at', now()->toDateString())
            ->selectRaw('kendala_reasons.label, COUNT(*) as total')
            ->groupBy('kendala_reasons.label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('admin.dashboard.kendala', ['topKendala' => $top]);
    }

    /**
     * FR-12: export ringkas (untuk detail per-PA lengkap, lihat
     * AgingReportController::export()).
     */
    public function export(Request $request)
    {
        $this->authorize('exportReport');

        $rows = PaOrder::with(['region', 'currentOfficer'])
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
            ->orderBy('pa_number')
            ->get();

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['PA Number', 'Nama Pelanggan', 'Wilayah', 'Status', 'Petugas', 'Aging (hari)']);

            foreach ($rows as $pa) {
                fputcsv($handle, [
                    $pa->pa_number,
                    $pa->customer_name,
                    $pa->region?->kabupaten_kota,
                    $pa->current_status,
                    $pa->currentOfficer?->name,
                    $pa->aging,
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, 'laporan-pa-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
