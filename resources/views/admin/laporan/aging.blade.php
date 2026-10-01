@extends('layouts.admin')

@php
	$active = 'dashboard';
	$title = 'Laporan Aging & SLA';
	$subtitle = 'Prioritas PA yang belum selesai';
	$priorityCounts = ['Normal' => 0, 'Perlu perhatian' => 0, 'Tinggi' => 0, 'Kritis (Over SLA)' => 0];
	$maxAging = 0;

	foreach ($rows as $row) {
		$priority = $row['priority'];
		if (isset($priorityCounts[$priority])) {
			$priorityCounts[$priority]++;
		}
		$maxAging = max($maxAging, (int) $row['aging']);
	}
@endphp

@section('title', 'Laporan Aging')

@section('content')
	<div class="space-y-5">
		<div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
			<div>
				<span class="page-kicker">Aging &amp; SLA</span>
				<h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Daftar Aging PA</h2>
				<p class="mt-1 text-sm text-slate-500">Threshold SLA aktif: {{ $sla->sla_days }} hari, {{ $sla->aging_green_max }} / {{ $sla->aging_yellow_max }} / {{ $sla->aging_orange_max }}</p>
			</div>

			@can('exportReport')
				<a href="{{ route('laporan.aging.export') }}" class="inline-flex items-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Ekspor CSV</a>
			@endcan
		</div>

		<form method="GET" action="{{ route('laporan.aging') }}" class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto_auto]">
			<select name="region_id" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
				<option value="">Semua wilayah</option>
				@foreach($regions as $region)
					<option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->kabupaten_kota }}</option>
				@endforeach
			</select>

			<select name="per_page" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
				@foreach([10, 15, 25, 50] as $value)
					<option value="{{ $value }}" @selected((string) request('per_page', 15) === (string) $value)>{{ $value }} / halaman</option>
				@endforeach
			</select>

			<button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Terapkan</button>
		</form>

		<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total PA</p>
				<p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $rows->count() }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Normal</p>
				<p class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $priorityCounts['Normal'] }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Perlu perhatian</p>
				<p class="mt-2 text-2xl font-extrabold text-amber-600">{{ $priorityCounts['Perlu perhatian'] }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Aging tertinggi</p>
				<p class="mt-2 text-2xl font-extrabold text-rose-500">{{ $maxAging }}d</p>
			</div>
		</div>

		<div class="page-panel overflow-hidden">
			<div>
				<table class="w-full text-left text-sm">
					<thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
						<tr>
							<th class="px-5 py-3 font-medium">No PA</th>
							<th class="px-5 py-3 font-medium">ID Pelanggan</th>
							<th class="px-5 py-3 font-medium">Wilayah</th>
							<th class="px-5 py-3 font-medium">Petugas</th>
							<th class="px-5 py-3 font-medium">Aging</th>
							<th class="px-5 py-3 font-medium">Status</th>
							<th class="px-5 py-3 font-medium">Prioritas</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-slate-100">
						@forelse ($rows as $row)
							@php $pa = $row['pa']; @endphp
							<tr class="hover:bg-slate-50/60">
								<td class="px-5 py-4 font-semibold text-slate-800">{{ $pa->pa_number }}</td>
								<td class="px-5 py-4 text-slate-500">{{ $pa->customer_id ?? '-' }}</td>
								<td class="px-5 py-4 text-slate-500">{{ $pa->region?->kabupaten_kota ?? '-' }}</td>
								<td class="px-5 py-4 text-slate-500">{{ $pa->currentOfficer?->name ?? 'Belum ditugaskan' }}</td>
								<td class="px-5 py-4 font-semibold text-slate-700">{{ $row['aging'] }} hari</td>
								<td class="px-5 py-4"><x-badge-status :status="$pa->current_status" /></td>
								<td class="px-5 py-4">
									@php
										$priority = $row['priority'];
										$levelMap = [
											'Normal' => 'green',
											'Perlu perhatian' => 'yellow',
											'Tinggi' => 'orange',
											'Kritis (Over SLA)' => 'red',
										];
									@endphp
									<x-badge-status :level="$levelMap[$priority] ?? 'slate'" :label="$priority" />
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400">Tidak ada PA yang masuk daftar aging.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			@if ($rows->hasPages())
				<div class="border-t border-slate-100 px-5 py-3">
					{{ $rows->links() }}
				</div>
			@endif
		</div>
	</div>
@endsection
