@extends('layouts.admin')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="page-kicker">Control Tower</span>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Monitoring &amp; Operasional</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan status PA, pembayaran, dan aktivitas terbaru.</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="page-panel p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total PA</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $summary['total_pa'] }}</div>
            <div class="mt-2 text-xs text-slate-400">Semua data aktif</div>
        </div>
        <div class="page-panel p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Sudah dibayar</div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $summary['paid'] }}</div>
            <div class="mt-2 text-xs text-emerald-600/70">Pembayaran valid</div>
        </div>
        <div class="page-panel p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">BAST diterbitkan</div>
            <div class="mt-2 text-2xl font-extrabold text-sky-600">{{ $summary['bast_issued'] }}</div>
            <div class="mt-2 text-xs text-sky-600/70">Dokumen final siap</div>
        </div>
        <div class="page-panel p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Dalam proses</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-600">{{ $summary['in_progress'] }}</div>
            <div class="mt-2 text-xs text-amber-600/70">Sedang dikerjakan</div>
        </div>
        <div class="page-panel p-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Butuh perhatian</div>
            <div class="mt-2 text-2xl font-extrabold text-rose-500">{{ $summary['pending'] + $summary['rejected'] + $summary['kendala'] }}</div>
            <div class="mt-2 text-xs text-rose-500/70">Pembayaran & kendala</div>
        </div>
    </div>

    <section class="page-panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Aktivitas Terbaru</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">No PA</th>
                        <th class="px-5 py-3 font-medium">ID Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Pembayaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recent as $item)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $item->pa_number }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $item->customer_id ?? '-' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $item->region->kabupaten_kota ?? '-' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ $item->current_status }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold
                                    @if(($item->payment_status ?? 'PENDING') === 'PAID') bg-emerald-100 text-emerald-700
                                    @elseif(($item->payment_status ?? 'PENDING') === 'REJECTED') bg-rose-100 text-rose-700
                                    @else bg-amber-100 text-amber-700 @endif">
                                    {{ $item->payment_status ?? 'PENDING' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data monitoring.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
