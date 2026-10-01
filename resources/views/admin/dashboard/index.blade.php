@extends('layouts.admin')

@php
    $active = 'dashboard';
    $title = 'Control Tower — Deaktivasi ICONNET';
    $subtitle = 'Monitoring operasional & tracking SLA penarikan perangkat ONT';
    $statusChartConfig = [
        'type' => 'doughnut',
        'data' => [
            'labels' => $statusChart['labels'],
            'datasets' => [[
                'data' => $statusChart['values'],
                'backgroundColor' => ['#94a3b8', '#00AEEF', '#F59E0B', '#10B981', '#ED1C24', '#be123c'],
                'borderWidth' => 0,
            ]],
        ],
        'options' => [
            'cutout' => '68%',
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'padding' => 16, 'font' => ['size' => 11]]]],
        ],
    ];
    $regionChartConfig = [
        'type' => 'bar',
        'data' => [
            'labels' => $regionChart['labels'],
            'datasets' => [[
                'label' => 'Selesai (%)',
                'data' => $regionChart['values'],
                'backgroundColor' => '#00AEEF',
                'borderRadius' => 7,
                'maxBarThickness' => 42,
            ]],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => ['y' => ['beginAtZero' => true, 'max' => 100], 'x' => ['grid' => ['display' => false]]],
            'plugins' => ['legend' => ['display' => false]],
        ],
    ];
@endphp

@section('title', 'Control Tower')

@section('content')
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <span class="page-kicker">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Control Tower
            </span>
            <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Ringkasan Hari Ini</h2>
            <p class="mt-1 text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>

        @can('exportReport')
            <a href="{{ route('reports.export') }}" class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">
                <x-dashboard-icon name="export" class="h-4 w-4" />
                Ekspor Laporan (.csv)
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3 border-b border-slate-200 pb-4">
        <label class="block text-xs font-semibold text-slate-600">KP<select name="kantor_perwakilan_id" class="mt-1 block min-w-52 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">Semua KP</option>@foreach($kantorPerwakilan as $kp)<option value="{{ $kp->id }}" @selected((string) ($filters['kantor_perwakilan_id'] ?? '') === (string) $kp->id)>{{ $kp->kode }} · {{ $kp->nama }}</option>@endforeach</select></label>
        <label class="block text-xs font-semibold text-slate-600">Tanggal PA dari<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="block text-xs font-semibold text-slate-600">Tanggal PA sampai<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Terapkan</button>
        <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Reset</a>
    </form>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <x-stat-card label="Total PA Deaktivasi" :value="number_format($total)" icon="list" tone="slate" />
        <x-stat-card label="Selesai / Done" :value="number_format($done).' ('.$statusPercentages['DONE'].'%)'" icon="list" tone="emerald" />
        <x-stat-card label="Diproses" :value="number_format($onProgress).' ('.$statusPercentages['ON_PROGRESS'].'%)'" icon="sync" tone="sky" />
        <x-stat-card label="Kendala" :value="number_format($kendala).' ('.$statusPercentages['KENDALA'].'%)'" icon="alert" tone="amber" />
        <x-stat-card label="Over SLA" :value="number_format($overSla)" icon="clock" tone="red" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <x-stat-card label="QC Pending" :value="number_format($pendingQc)" icon="clock" tone="amber" />
        <x-stat-card label="BAST final" :value="number_format($bastIssued)" icon="list" tone="sky" />
        <x-stat-card label="Pembayaran tercatat" :value="'Rp '.number_format($paymentTotal, 0, ',', '.')" icon="list" tone="emerald" />
        <x-stat-card label="K3 awal & akhir lengkap" :value="$k3Compliance['percentage'].'%'" icon="check" tone="emerald" />
        <x-stat-card label="Auto-reject SN" :value="number_format($snAutoRejected)" icon="alert" tone="red" />
    </div>

    <section class="mt-4 page-panel p-5">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-slate-900">Funnel operasional</h3>
            <p class="text-xs text-slate-400">Jumlah PA pada setiap tahap siklus saat ini.</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach($funnel as $stage)
                <div class="border-l-2 border-sky-500 pl-3">
                    <p class="text-xs text-slate-500">{{ $stage['label'] }}</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($stage['count']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="page-panel p-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Assigned</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">{{ number_format($todaySummary['assigned']) }}</p>
        </div>
        <div class="page-panel p-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Done</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-emerald-600">{{ number_format($todaySummary['done']) }}</p>
        </div>
        <div class="page-panel p-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">On Progress</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-sky-600">{{ number_format($todaySummary['progress']) }}</p>
        </div>
        <div class="page-panel p-4">
            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Kendala</p>
            <p class="mt-2 text-2xl font-extrabold tracking-tight text-amber-600">{{ number_format($todaySummary['kendala']) }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-5">
        <section class="app-card p-5 xl:col-span-2" data-reveal>
            <div class="mb-4"><h3 class="text-sm font-semibold text-slate-900">Komposisi Status PA</h3><p class="text-xs text-slate-400">Distribusi seluruh PA saat ini.</p></div>
            <div class="mx-auto max-w-[280px]"><canvas data-chart='@json($statusChartConfig)'></canvas></div>
        </section>
        <section class="app-card p-5 xl:col-span-3" data-reveal>
            <div class="mb-4"><h3 class="text-sm font-semibold text-slate-900">Progress per Wilayah</h3><p class="text-xs text-slate-400">Persentase PA selesai terhadap total per wilayah.</p></div>
            <div class="h-72"><canvas data-chart='@json($regionChartConfig)'></canvas></div>
        </section>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Progress per wilayah --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Progres Deaktivasi per Wilayah</h3>
                <p class="text-xs text-slate-400">Realisasi PA selesai dibanding total PA per wilayah operasional.</p>
            </div>

            @if ($regions->isEmpty())
                <p class="py-6 text-center text-sm text-slate-400">Belum ada data PA untuk ditampilkan.</p>
            @else
                <div class="space-y-4">
                    @foreach ($regions as $region)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ $region['wilayah'] ?? 'Tanpa Wilayah' }}</span>
                                <span class="text-slate-400">{{ $region['done'] }} / {{ $region['total'] }} PA ({{ $region['percentage'] }}%)</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-sky-500" style="width: {{ $region['percentage'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top kendala --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Top Kendala Hari Ini</h3>
                <p class="text-xs text-slate-400">Alasan kendala terbanyak yang dilaporkan petugas hari ini.</p>
            </div>

            @php $maxKendala = $topKendala->max('total') ?: 1; @endphp

            @if ($topKendala->isEmpty())
                <p class="py-6 text-center text-sm text-slate-400">Belum ada kendala dilaporkan hari ini.</p>
            @else
                <div class="space-y-3">
                    @foreach ($topKendala as $item)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">{{ $item->label }}</span>
                                <span class="text-slate-400">{{ $item->total }} kasus</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-1.5 rounded-full bg-amber-500" style="width: {{ round($item->total / $maxKendala * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Daftar prioritas eskalasi & over SLA --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-5">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Prioritas Eskalasi &amp; Over SLA</h3>
                <p class="text-xs text-slate-400">20 PA dengan aging tertinggi yang belum selesai. Halaman "Laporan Aging &amp; SLA" lengkap menyusul di tahap berikutnya.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] uppercase tracking-wide text-slate-400">
                        <th class="px-5 py-3 font-medium">No PA</th>
                        <th class="px-5 py-3 font-medium">ID Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Petugas</th>
                        <th class="px-5 py-3 font-medium">Aging</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Prioritas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($escalations as $row)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $row['pa']->pa_number }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $row['pa']->customer_id ?? '-' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $row['pa']->region?->kabupaten_kota ?? '-' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $row['pa']->currentOfficer?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="px-5 py-3 font-medium text-slate-700">{{ $row['aging'] }} hari</td>
                            <td class="px-5 py-3"><x-badge-status :status="$row['pa']->current_status" /></td>
                            <td class="px-5 py-3">
                                <x-badge-status :level="$row['priority']['level']" :label="$row['priority']['label']" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400">
                                Tidak ada PA yang perlu eskalasi saat ini. 🎉
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-5"><h3 class="text-sm font-semibold text-slate-900">Performa Petugas Hari Ini</h3><p class="text-xs text-slate-400">Tugas yang tertunda dan status pekerjaan per petugas aktif.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-100 text-[11px] uppercase tracking-wide text-slate-400"><th class="px-5 py-3 font-medium">Petugas</th><th class="px-5 py-3 font-medium">Wilayah</th><th class="px-5 py-3 font-medium">Assigned</th><th class="px-5 py-3 font-medium">Done</th><th class="px-5 py-3 font-medium">Progress</th><th class="px-5 py-3 font-medium">Kendala</th></tr></thead><tbody class="divide-y divide-slate-50">@forelse ($officerProgress as $officer)<tr class="hover:bg-slate-50/60"><td class="px-5 py-3 font-medium text-slate-700">{{ $officer->name }}</td><td class="px-5 py-3 text-slate-500">{{ $officer->region?->kabupaten_kota ?? '-' }}</td><td class="px-5 py-3 text-slate-500">{{ $officer->assigned_today_count }}</td><td class="px-5 py-3 text-emerald-600">{{ $officer->done_count }}</td><td class="px-5 py-3 text-sky-600">{{ $officer->progress_count }}</td><td class="px-5 py-3 {{ $officer->kendala_count > 0 ? 'font-semibold text-red-600' : 'text-slate-500' }}">{{ $officer->kendala_count }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">Belum ada petugas aktif.</td></tr>@endforelse</tbody></table></div>
    </div>
@endsection
