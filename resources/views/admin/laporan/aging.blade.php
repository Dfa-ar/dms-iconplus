@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Laporan Aging & SLA';
	$subtitle = 'Prioritas PA yang belum selesai';
@endphp

@section('title', 'Laporan Aging')

@section('content')
	<div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
		<div>
			<h2 class="text-lg font-bold text-slate-900">Daftar Aging PA</h2>
			<p class="text-sm text-slate-500">Threshold SLA aktif: {{ $sla->sla_days }} hari, {{ $sla->aging_green_max }} / {{ $sla->aging_yellow_max }} / {{ $sla->aging_orange_max }}</p>
		</div>

		@can('exportReport')
			<a href="{{ route('laporan.aging.export') }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Ekspor CSV</a>
		@endcan
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">PA</th>
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
							<td class="px-5 py-4">
								<p class="font-medium text-slate-700">{{ $pa->pa_number }}</p>
								<p class="text-xs text-slate-400">{{ $pa->customer_name }}</p>
							</td>
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
							<td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">Tidak ada PA yang masuk daftar aging.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
@endsection
