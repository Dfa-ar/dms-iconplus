@extends('layouts.app')

@php
	$active = 'pa';
	$title = 'Master Data PA';
	$subtitle = 'Cari, filter, dan pantau seluruh data deaktivasi pelanggan';
@endphp

@section('title', 'Master Data PA')

@section('content')
	<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
		<div>
			<h2 class="text-lg font-bold text-slate-900">Master Data PA</h2>
			<p class="text-sm text-slate-500">{{ number_format($paOrders->total()) }} data ditemukan</p>
		</div>
		@can('uploadPa')
			<div class="flex gap-2"><a href="{{ route('reports.export', request()->query()) }}" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a><a href="{{ route('admin.pa.upload') }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Upload Data</a></div>
		@endcan
	</div>

	<form method="GET" action="{{ route('admin.pa.index') }}" class="mb-5 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5">
		<input name="search" value="{{ request('search') }}" placeholder="Cari nomor PA, pelanggan, atau ID" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
		<select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
			<option value="">Semua status</option>
			@foreach ($statuses as $status)
				<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
			@endforeach
		</select>
		<select name="region_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
			<option value="">Semua wilayah</option>
			@foreach ($regions as $region)
				<option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->kabupaten_kota }}{{ $region->kecamatan ? ' - '.$region->kecamatan : '' }}</option>
			@endforeach
		</select>
		<select name="officer_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
			<option value="">Semua petugas</option>
			@foreach ($officers as $officer)
				<option value="{{ $officer->id }}" @selected((string) request('officer_id') === (string) $officer->id)>{{ $officer->name }}</option>
			@endforeach
		</select>
		<div class="flex gap-2">
			<input type="date" name="pa_date" value="{{ request('pa_date') }}" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
			<button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
		</div>
	</form>

	<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">PA / Pelanggan</th>
						<th class="px-5 py-3 font-medium">Wilayah</th>
						<th class="px-5 py-3 font-medium">Petugas</th>
						<th class="px-5 py-3 font-medium">Tanggal PA</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($paOrders as $paOrder)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4"><p class="font-medium text-slate-800">{{ $paOrder->pa_number }}</p><p class="text-xs text-slate-400">{{ $paOrder->customer_name }} · {{ $paOrder->customer_id }}</p></td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->currentOfficer?->name ?? 'Belum ditugaskan' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->pa_date?->format('d/m/Y') ?? '-' }}</td>
							<td class="px-5 py-4"><x-badge-status :status="$paOrder->current_status" /></td>
							<td class="px-5 py-4 text-right"><a href="{{ route('admin.pa.show', $paOrder) }}" class="font-medium text-sky-600 hover:text-sky-800">Detail</a></td>
						</tr>
					@empty
						<tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data PA yang sesuai filter.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		@if ($paOrders->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">{{ $paOrders->links() }}</div>
		@endif
	</div>
@endsection
