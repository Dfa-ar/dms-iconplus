<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaOrder;
use App\Models\SlaSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AgingReportController extends Controller
{
    /**
     * Blueprint 11.2: warna & prioritas aging diambil dari sla_settings
     * (bisa diubah Super Admin), bukan hardcode di kode.
     */
    public function index(Request $request)
    {
        $this->authorize('viewDashboard');

        $sla = SlaSetting::first() ?? new SlaSetting([
            'sla_days' => 14, 'aging_green_max' => 2, 'aging_yellow_max' => 6, 'aging_orange_max' => 13,
        ]);

        $query = PaOrder::with(['region', 'currentOfficer'])
            ->whereNotIn('current_status', [PaOrder::STATUS_DONE]);

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->input('region_id'));
        }

        $paOrders = $query->get()
            ->sortByDesc('aging')
            ->map(fn (PaOrder $pa) => [
                'pa' => $pa,
                'aging' => $pa->aging,
                'priority' => $this->priorityFor($pa->aging, $sla),
            ]);

        return view('admin.laporan.aging', [
            'rows' => $paOrders,
            'sla' => $sla,
        ]);
    }

    public function export()
    {
        $this->authorize('exportReport');

        $sla = SlaSetting::first() ?? new SlaSetting([
            'sla_days' => 14, 'aging_green_max' => 2, 'aging_yellow_max' => 6, 'aging_orange_max' => 13,
        ]);

        $rows = PaOrder::with(['region', 'currentOfficer'])
            ->whereNotIn('current_status', [PaOrder::STATUS_DONE])
            ->get()
            ->sortByDesc('aging');

        $callback = function () use ($rows, $sla) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['PA Number', 'Nama Pelanggan', 'Wilayah', 'Petugas', 'Status', 'Aging (hari)', 'Prioritas']);

            foreach ($rows as $pa) {
                fputcsv($handle, [
                    $pa->pa_number,
                    $pa->customer_name,
                    $pa->region?->kabupaten_kota,
                    $pa->currentOfficer?->name,
                    $pa->current_status,
                    $pa->aging,
                    $this->priorityFor($pa->aging, $sla),
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, 'laporan-aging-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function priorityFor(int $aging, SlaSetting $sla): string
    {
        return match (true) {
            $aging <= $sla->aging_green_max => 'Normal',
            $aging <= $sla->aging_yellow_max => 'Perlu perhatian',
            $aging <= $sla->aging_orange_max => 'Tinggi',
            default => 'Kritis (Over SLA)',
        };
    }
}
