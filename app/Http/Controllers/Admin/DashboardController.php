<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KantorPerwakilan;
use App\Models\KendalaReason;
use App\Models\Assignment;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\PaymentItem;
use App\Models\Region;
use App\Models\SlaSetting;
use App\Models\StatusLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewDashboard');

        $filters = $request->validate([
            'kantor_perwakilan_id' => ['nullable', 'integer', 'exists:kantor_perwakilan,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
        $kantorPerwakilan = KantorPerwakilan::orderBy('nama')->get();

        $statusCounts = $this->filteredPaOrders($filters)
            ->selectRaw('current_status, COUNT(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');
        $pendingQc = $this->filteredPaOrders($filters)
            ->where(function (Builder $query) {
                $query->where('qc_status', PaOrder::QC_STATUS_PENDING)
                    ->orWhere(function (Builder $query) {
                        $query->where('current_status', PaOrder::BLUEPRINT_STATUS_PENDING_QC)
                            ->whereNull('qc_status');
                    });
            })
            ->count();
        $doneStatuses = [
            PaOrder::STATUS_DONE,
            PaOrder::STATUS_CLOSE_ICRM,
            PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            PaOrder::QC_STATUS_PASSED,
            PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
            PaOrder::BLUEPRINT_STATUS_BAST_VOID,
            PaOrder::BLUEPRINT_STATUS_PAID,
        ];
        $doneCount = collect($doneStatuses)->sum(fn ($status) => $statusCounts[$status] ?? 0);
        $qcPassed = $this->filteredPaOrders($filters)
            ->where(function (Builder $query) {
                $query->where('qc_status', PaOrder::QC_STATUS_PASSED)
                    ->orWhereIn('current_status', [
                        PaOrder::QC_STATUS_PASSED,
                        PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
                        PaOrder::BLUEPRINT_STATUS_BAST_VOID,
                        PaOrder::BLUEPRINT_STATUS_PAID,
                    ]);
            })
            ->count();

        $sla = SlaSetting::first() ?? new SlaSetting(['sla_days' => 14]);

        $regions = $this->filteredPaOrders($filters)
            ->join('regions', 'regions.id', '=', 'pa_orders.region_id')
            ->selectRaw(
                'regions.kabupaten_kota as wilayah, COUNT(*) as total, SUM(CASE WHEN current_status IN (?, ?, ?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as done',
                $doneStatuses
            )
            ->groupBy('wilayah')
            ->get()
            ->map(fn ($row) => [
                'wilayah' => $row->wilayah,
                'total' => (int) $row->total,
                'done' => (int) $row->done,
                'percentage' => $row->total > 0 ? round($row->done / $row->total * 100, 1) : 0,
            ]);

        $topKendala = StatusLog::query()
            ->join('kendala_reasons', 'kendala_reasons.id', '=', 'status_logs.kendala_reason_id')
            ->where('status_logs.to_status', PaOrder::STATUS_KENDALA)
            ->whereDate('status_logs.changed_at', now()->toDateString())
            ->whereHas('paOrder', fn (Builder $query) => $this->applyDashboardFilters($query, $filters))
            ->selectRaw('kendala_reasons.label, COUNT(*) as total')
            ->groupBy('kendala_reasons.label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $escalations = $this->filteredPaOrders($filters)
            ->with(['region', 'currentOfficer'])
            ->whereNotIn('current_status', $doneStatuses)
            ->get()
            ->sortByDesc('aging')
            ->take(20)
            ->map(fn (PaOrder $pa) => [
                'pa' => $pa,
                'aging' => $pa->aging,
                'priority' => $this->priorityFor($pa->aging, $sla),
            ]);

        $overSla = $this->filteredPaOrders($filters)
            ->whereNotIn('current_status', $doneStatuses)
            ->whereDate('pa_date', '<=', now()->subDays($sla->sla_days))
            ->count();

        $total = array_sum($statusCounts->toArray());
        $statusPercentages = collect([
            PaOrder::STATUS_UNASSIGNED => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
            PaOrder::STATUS_ASSIGNED => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
            PaOrder::STATUS_ON_PROGRESS => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            PaOrder::STATUS_DONE => $doneCount,
            PaOrder::STATUS_KENDALA => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
        ])->map(fn ($count) => $total > 0 ? round($count / $total * 100, 1) : 0);

        $today = now()->toDateString();
        $todayStatusCounts = $this->filteredPaOrders($filters)
            ->whereDate('assigned_date', $today)
            ->selectRaw('current_status, COUNT(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');
        $todaySummary = [
            'assigned' => Assignment::whereDate('assign_date', $today)
                ->whereHas('paOrder', fn ($query) => $this->applyDashboardFilters($query, $filters))
                ->count(),
            'done' => $this->filteredPaOrders($filters)->whereDate('completed_at', $today)->whereIn('current_status', $doneStatuses)->count(),
            'progress' => $todayStatusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            'kendala' => $todayStatusCounts[PaOrder::STATUS_KENDALA] ?? 0,
        ];

        $officerProgress = Officer::query()
            ->with('region')
            ->where('is_active', true)
            ->withCount([
                'assignments as assigned_today_count' => fn ($query) => $query->whereDate('assign_date', $today),
                'currentPaOrders as assigned_count' => fn ($query) => $this->applyDashboardFilters($query, $filters)->where('current_status', PaOrder::STATUS_ASSIGNED)->whereDate('assigned_date', $today),
                'currentPaOrders as progress_count' => fn ($query) => $this->applyDashboardFilters($query, $filters)->where('current_status', PaOrder::STATUS_ON_PROGRESS)->whereDate('assigned_date', $today),
                'currentPaOrders as kendala_count' => fn ($query) => $this->applyDashboardFilters($query, $filters)->where('current_status', PaOrder::STATUS_KENDALA)->whereDate('assigned_date', $today),
                'currentPaOrders as done_count' => fn ($query) => $this->applyDashboardFilters($query, $filters)->whereIn('current_status', $doneStatuses)->whereDate('completed_at', $today),
            ])
            ->orderBy('name')
            ->get();

        $totalPa = $this->filteredPaOrders($filters)->count();
        $k3Compliant = $this->filteredPaOrders($filters)
            ->whereHas('evidences', fn ($query) => $query->where('type', 'k3_awal'))
            ->whereHas('evidences', fn ($query) => $query->where('type', 'k3_akhir'))
            ->count();
        $snAutoRejected = $this->filteredPaOrders($filters)
            ->where('qc_status', PaOrder::QC_STATUS_REJECTED)
            ->whereHas('qcRejectReason', fn ($query) => $query->where('code', 'sn_ont_tidak_terbaca'))
            ->count();
        $bastIssued = $this->filteredPaOrders($filters)
            ->whereHas('bastDocument', fn ($query) => $query->where('status', 'FINAL'))
            ->count();
        $paymentTotal = PaymentItem::query()
            ->whereHas('paOrder', fn ($query) => $this->applyDashboardFilters($query, $filters))
            ->sum('amount');
        $funnel = [
            ['label' => 'Belum ditugaskan', 'count' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0],
            ['label' => 'Ditugaskan', 'count' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0],
            ['label' => 'Dikerjakan', 'count' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0],
            ['label' => 'QC pending', 'count' => $pendingQc],
            ['label' => 'QC lolos / BAST', 'count' => $qcPassed],
            ['label' => 'Lunas', 'count' => $statusCounts[PaOrder::BLUEPRINT_STATUS_PAID] ?? 0],
        ];

        return view('admin.dashboard.index', [
            'total' => $total,
            'filters' => $filters,
            'kantorPerwakilan' => $kantorPerwakilan,
            'pendingQc' => $pendingQc,
            'bastIssued' => $bastIssued,
            'paymentTotal' => (float) $paymentTotal,
            'k3Compliance' => [
                'count' => $k3Compliant,
                'percentage' => $totalPa > 0 ? round($k3Compliant / $totalPa * 100, 1) : 0,
            ],
            'snAutoRejected' => $snAutoRejected,
            'done' => $doneCount,
            'funnel' => $funnel,
            'onProgress' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            'kendala' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
            'overSla' => $overSla,
            'statusPercentages' => $statusPercentages,
            'statusChart' => [
                'labels' => ['Belum Ditugaskan', 'Ditugaskan', 'Diproses', 'Selesai', 'Kendala', 'QC Ditolak'],
                'values' => [
                    $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                    $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                    $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                    $doneCount,
                    $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                    $statusCounts[PaOrder::QC_STATUS_REJECTED] ?? 0,
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

    private function filteredPaOrders(array $filters): Builder
    {
        return $this->applyDashboardFilters(PaOrder::query(), $filters);
    }

    private function applyDashboardFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['kantor_perwakilan_id'] ?? null, function (Builder $query, $officeId) {
                $query->where(function (Builder $query) use ($officeId) {
                    $query->where('pa_orders.kantor_perwakilan_id', $officeId)
                        ->orWhere(function (Builder $query) use ($officeId) {
                            $query->whereNull('pa_orders.kantor_perwakilan_id')
                                ->whereHas('region', fn (Builder $regionQuery) => $regionQuery->where('kantor_perwakilan_id', $officeId));
                        });
                });
            })
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_orders.pa_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('pa_orders.pa_date', '<=', $date));
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
                regions.kabupaten_kota as wilayah,
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

    public function map()
    {
        $this->authorize('viewDashboard');

        return view('admin.dashboard.map', [
            'markers' => $this->mapSummaryPayload(),
            'clusters' => $this->mapClustersPayload(),
            'officerMarkers' => $this->mapOfficerPayload(),
            'heatmap' => $this->mapHeatmapPayload(),
        ]);
    }

    public function mapSummary()
    {
        $this->authorize('viewDashboard');

        return response()->json([
            'data' => $this->mapSummaryPayload(),
            'clusters' => $this->mapClustersPayload(),
            'officers' => $this->mapOfficerPayload(),
            'heatmap' => $this->mapHeatmapPayload(),
        ]);
    }

    private function mapOfficerPayload(): array
    {
        $regionCenters = Region::query()
            ->areas()
            ->whereNull('kecamatan')
            ->whereNull('kelurahan')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->keyBy('kabupaten_kota');

        return Officer::query()
            ->where('is_active', true)
            ->with(['region', 'kantorPerwakilan'])
            ->get()
            ->map(function (Officer $officer) use ($regionCenters) {
                $center = $regionCenters->get($officer->region?->kabupaten_kota);
                $latitude = $officer->region?->lat ?? $center?->lat ?? $officer->kantorPerwakilan?->latitude;
                $longitude = $officer->region?->lng ?? $center?->lng ?? $officer->kantorPerwakilan?->longitude;
                if ($latitude === null || $longitude === null) {
                    return null;
                }

                return [
                    'id' => $officer->id,
                    'name' => $officer->name,
                    'employee_code' => $officer->employee_code,
                    'region' => $officer->region?->kabupaten_kota ?? $officer->kantorPerwakilan?->nama ?? '-',
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function mapHeatmapPayload(): array
    {
        $mapRegions = Region::query()
            ->areas()
            ->whereNull('kecamatan')
            ->whereNull('kelurahan')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->selectRaw('MIN(id) as id, kabupaten_kota, lat, lng')
            ->groupBy('kabupaten_kota', 'lat', 'lng');

        return PaOrder::query()
            ->join('regions as pa_regions', 'pa_regions.id', '=', 'pa_orders.region_id')
            ->joinSub($mapRegions, 'map_regions', fn ($join) => $join->on('map_regions.kabupaten_kota', '=', 'pa_regions.kabupaten_kota'))
            ->selectRaw('map_regions.lat as lat, map_regions.lng as lng, COUNT(pa_orders.id) as weight')
            ->groupBy('map_regions.kabupaten_kota', 'map_regions.lat', 'map_regions.lng')
            ->get()
            ->map(fn ($row) => [(float) $row->lat, (float) $row->lng, (int) $row->weight])
            ->all();
    }

    private function mapSummaryPayload(): array
    {
        $data = [];
        /** @var \Illuminate\Database\Eloquent\Collection<int, KantorPerwakilan> $kantorPerwakilan */
        $kantorPerwakilan = KantorPerwakilan::query()
            ->with(['regions', 'officers' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('nama')
            ->get();

        if ($kantorPerwakilan->isNotEmpty()) {
            foreach ($kantorPerwakilan as $kp) {
                $regionIds = $kp->regions()->pluck('id')->all();
                $statusCounts = PaOrder::query()
                    ->where(function ($query) use ($kp, $regionIds) {
                        $query->where('kantor_perwakilan_id', $kp->id);

                        if (! empty($regionIds)) {
                            $query->orWhereIn('region_id', $regionIds);
                        }
                    })
                    ->selectRaw('current_status, COUNT(*) as total')
                    ->groupBy('current_status')
                    ->pluck('total', 'current_status')
                    ->toArray();

                $primaryRegion = $kp->regions()->orderBy('kabupaten_kota')->first();
                $assignmentRoute = $primaryRegion
                    ? route('admin.assignments.index', ['region_id' => $primaryRegion->id])
                    : route('admin.assignments.index');

                $data[] = [
                    'id' => $kp->id,
                    'kind' => 'kp',
                    'name' => $kp->nama,
                    'display_name' => $kp->nama,
                    'lat' => (float) ($kp->latitude ?? $primaryRegion?->lat ?? -6.9147),
                    'lng' => (float) ($kp->longitude ?? $primaryRegion?->lng ?? 107.6098),
                    'area' => $primaryRegion?->kabupaten_kota ?? 'Kantor Perwakilan',
                    'district' => $primaryRegion?->kabupaten_kota ?? $kp->nama,
                    'officer_count' => $kp->officers()->where('is_active', true)->count(),
                    'total_pa' => array_sum($statusCounts),
                    'assignment_route' => $assignmentRoute,
                    'status' => [
                        'UNASSIGNED' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                        'ASSIGNED' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                        'ON_PROGRESS' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                        'KENDALA' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                        'DONE' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                    ],
                ];
            }

        }

        $regions = Region::query()
            ->areas()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->withCount(['officers as officer_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('kabupaten_kota')
            ->get();

        foreach ($regions as $region) {
            $statusCounts = $region->paOrders()
                ->selectRaw('current_status, COUNT(*) as total')
                ->groupBy('current_status')
                ->pluck('total', 'current_status')
                ->toArray();

            $data[] = [
                'id' => $region->id,
                'kind' => 'region',
                'name' => $region->kabupaten_kota,
                'display_name' => $region->kabupaten_kota,
                'lat' => (float) $region->lat,
                'lng' => (float) $region->lng,
                'area' => $region->kabupaten_kota . ', Jawa Barat',
                'district' => $region->kabupaten_kota,
                'officer_count' => (int) $region->officer_count,
                'total_pa' => array_sum($statusCounts),
                'assignment_route' => route('admin.assignments.index', ['region_id' => $region->id]),
                'status' => [
                    'UNASSIGNED' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                    'ASSIGNED' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                    'ON_PROGRESS' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                    'KENDALA' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                    'DONE' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                ],
            ];
        }

        return $data !== [] ? $data : $this->mapSummaryFallbackPayload();
    }

    private function mapSummaryFallbackPayload(): array
    {
        $base = [
            ['name' => 'Bandung Raya', 'lat' => -6.9147, 'lng' => 107.6098, 'area' => 'Bandung, Cimahi, Kabupaten Bandung'],
            ['name' => 'Cirebon', 'lat' => -6.7063, 'lng' => 108.5570, 'area' => 'Kota Cirebon, Kabupaten Cirebon'],
            ['name' => 'Tasikmalaya', 'lat' => -7.3274, 'lng' => 108.2207, 'area' => 'Kota Tasikmalaya, Kabupaten Tasikmalaya'],
            ['name' => 'Purwakarta', 'lat' => -6.5397, 'lng' => 107.4437, 'area' => 'Kabupaten Purwakarta'],
            ['name' => 'Sukabumi', 'lat' => -6.9186, 'lng' => 106.9263, 'area' => 'Kabupaten & Kota Sukabumi'],
        ];

        $data = [];
        foreach ($base as $point) {
            $officerCount = Officer::query()
                ->join('regions', 'regions.id', '=', 'officers.region_id')
                ->where('regions.parent_group', $point['name'])
                ->where('officers.is_active', true)
                ->count();

            $statusCounts = PaOrder::query()
                ->join('regions', 'regions.id', '=', 'pa_orders.region_id')
                ->where('regions.parent_group', $point['name'])
                ->selectRaw('current_status, COUNT(*) as total')
                ->groupBy('current_status')
                ->pluck('total', 'current_status')
                ->toArray();

            $data[] = [
                'id' => null,
                'kind' => 'cluster',
                'name' => $point['name'],
                'display_name' => $point['name'],
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'area' => $point['area'],
                'district' => $point['name'],
                'officer_count' => $officerCount,
                'total_pa' => array_sum($statusCounts),
                'assignment_route' => route('admin.assignments.index'),
                'status' => [
                    'UNASSIGNED' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                    'ASSIGNED' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                    'ON_PROGRESS' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                    'KENDALA' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                    'DONE' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                ],
            ];
        }

        return $data;
    }

    private function mapClustersPayload(): array
    {
        $kantorPerwakilan = KantorPerwakilan::query()
            ->with(['regions', 'officers' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('nama')
            ->get();

        if ($kantorPerwakilan->isNotEmpty()) {
            return $kantorPerwakilan
                ->map(function (KantorPerwakilan $kp) {
                    $regionIds = $kp->regions()->pluck('id')->all();
                    $statusCounts = PaOrder::query()
                        ->where(function ($query) use ($kp, $regionIds) {
                            $query->where('kantor_perwakilan_id', $kp->id);

                            if (! empty($regionIds)) {
                                $query->orWhereIn('region_id', $regionIds);
                            }
                        })
                        ->selectRaw('current_status, COUNT(*) as total')
                        ->groupBy('current_status')
                        ->pluck('total', 'current_status')
                        ->toArray();

                    return [
                        'id' => $kp->id,
                        'name' => $kp->nama,
                        'label' => $kp->nama,
                        'lat' => (float) ($kp->latitude ?? -6.9147),
                        'lng' => (float) ($kp->longitude ?? 107.6098),
                        'officer_count' => (int) $kp->officers()->where('is_active', true)->count(),
                        'active_officer_count' => (int) $kp->officers()->where('is_active', true)->count(),
                        'total_pa' => array_sum($statusCounts),
                        'status' => [
                            'UNASSIGNED' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                            'ASSIGNED' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                            'ON_PROGRESS' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                            'KENDALA' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                            'DONE' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                        ],
                    ];
                })
                ->values()
                ->all();
        }

        $clusters = Region::query()
            ->where('is_office', true)
            ->withCount(['officers as active_officer_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->map(function (Region $region) {
                $statusCounts = PaOrder::query()
                    ->where('region_id', $region->id)
                    ->orWhereHas('region', fn ($query) => $query->where('parent_region_id', $region->id))
                    ->selectRaw('current_status, COUNT(*) as total')
                    ->groupBy('current_status')
                    ->pluck('total', 'current_status')
                    ->toArray();

                return [
                    'id' => $region->id,
                    'name' => $region->office_name ?: $region->kabupaten_kota,
                    'label' => $region->kabupaten_kota,
                    'lat' => (float) ($region->lat ?? -6.9147),
                    'lng' => (float) ($region->lng ?? 107.6098),
                    'officer_count' => (int) $region->active_officer_count,
                    'active_officer_count' => (int) $region->active_officer_count,
                    'total_pa' => array_sum($statusCounts),
                    'status' => [
                        'UNASSIGNED' => $statusCounts[PaOrder::STATUS_UNASSIGNED] ?? 0,
                        'ASSIGNED' => $statusCounts[PaOrder::STATUS_ASSIGNED] ?? 0,
                        'ON_PROGRESS' => $statusCounts[PaOrder::STATUS_ON_PROGRESS] ?? 0,
                        'KENDALA' => $statusCounts[PaOrder::STATUS_KENDALA] ?? 0,
                        'DONE' => $statusCounts[PaOrder::STATUS_DONE] ?? 0,
                    ],
                ];
            })
            ->values()
            ->all();

        return $clusters;
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
