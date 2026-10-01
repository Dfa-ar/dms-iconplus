<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaOrder;

class MonitoringController extends Controller
{
    public function index()
    {
        $statusBreakdown = PaOrder::selectRaw('current_status, COUNT(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $paymentBreakdown = PaOrder::selectRaw('payment_status, COUNT(*) as total')
            ->whereNotNull('payment_status')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $recent = PaOrder::with(['region', 'currentOfficer.user'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $summary = [
            'total_pa' => PaOrder::count(),
            'paid' => $paymentBreakdown['PAID'] ?? 0,
            'pending' => $paymentBreakdown['PENDING'] ?? 0,
            'rejected' => $paymentBreakdown['REJECTED'] ?? 0,
            'in_progress' => $statusBreakdown[PaOrder::STATUS_ON_PROGRESS] ?? 0,
            'done' => $statusBreakdown[PaOrder::STATUS_DONE] ?? 0,
            'kendala' => $statusBreakdown[PaOrder::STATUS_KENDALA] ?? 0,
            'bast_issued' => PaOrder::whereNotNull('bast_document_id')->count(),
        ];

        return view('admin.monitoring.index', [
            'statusBreakdown' => $statusBreakdown,
            'paymentBreakdown' => $paymentBreakdown,
            'summary' => $summary,
            'recent' => $recent,
        ]);
    }
}
