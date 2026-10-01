@extends('layouts.admin')

@php
	$active = 'pa';
	$title = 'Master Data PA';
	$subtitle = 'Cari, filter, dan pantau seluruh data deaktivasi pelanggan';
@endphp

@section('title', 'Master Data PA')

@section('content')
	<div class="space-y-5">
		<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
			<div>
				<span class="page-kicker">Master Data</span>
				<h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Master Data PA</h2>
				<p class="mt-1 text-sm text-slate-500">{{ number_format($paOrders->total()) }} data ditemukan</p>
			</div>
			@can('uploadPa')
				<div class="flex flex-wrap gap-2">
					<a href="{{ route('reports.export', request()->query()) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a>
					<a href="{{ route('admin.pa.upload') }}" class="inline-flex items-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Upload Data</a>
				</div>
			@endcan
		</div>

		<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total PA</p>
				<p class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($paOrders->total()) }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Assigned</p>
				<p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $paOrders->where('current_status', 'ASSIGNED')->count() }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">On Progress</p>
				<p class="mt-2 text-2xl font-extrabold text-amber-600">{{ $paOrders->where('current_status', 'ON_PROGRESS')->count() }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Done</p>
				<p class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $paOrders->where('current_status', 'DONE')->count() }}</p>
			</div>
		</div>

		<form method="GET" action="{{ route('admin.pa.index') }}" class="page-panel mb-5 grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-5">
		<input name="search" value="{{ request('search') }}" placeholder="Cari nomor PA, pelanggan, atau ID" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-sky-400 focus:bg-white">
		<select name="status" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
			<option value="">Semua status</option>
			@foreach ($statuses as $status)
				<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
			@endforeach
		</select>
		<select name="region_id" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
			<option value="">Semua wilayah</option>
			@foreach ($regions as $region)
				<option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->kabupaten_kota }}{{ $region->kecamatan ? ' - '.$region->kecamatan : '' }}</option>
			@endforeach
		</select>
		<select name="officer_id" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
			<option value="">Semua petugas</option>
			@foreach ($officers as $officer)
				<option value="{{ $officer->id }}" @selected((string) request('officer_id') === (string) $officer->id)>{{ $officer->name }}</option>
			@endforeach
		</select>
		<div class="flex gap-2">
			<input type="date" name="pa_date" value="{{ request('pa_date') }}" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
			<button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
		</div>
	</form>

	<div class="page-panel overflow-hidden">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">No PA</th>
						<th class="px-5 py-3 font-medium">ID Pelanggan</th>
						<th class="px-5 py-3 font-medium">Wilayah</th>
						<th class="px-5 py-3 font-medium">Petugas</th>
						<th class="px-5 py-3 font-medium">Tanggal PA</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($paOrders as $paOrder)
						<tr class="hover:bg-slate-50/70">
							<td class="px-5 py-4">
								<p class="font-semibold text-slate-800">{{ $paOrder->pa_number }}</p>
								<p class="mt-1 text-xs text-slate-400">{{ $paOrder->customer_name }}</p>
							</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->customer_id ?? '-' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->currentOfficer?->name ?? 'Belum ditugaskan' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $paOrder->pa_date?->format('d/m/Y') ?? '-' }}</td>
							<td class="px-5 py-4"><x-badge-status :status="$paOrder->current_status" /></td>
							<td class="px-5 py-4 text-right"><a href="{{ route('admin.pa.show', $paOrder) }}" class="inline-flex items-center rounded-lg bg-sky-50 px-2.5 py-1.5 text-sm font-semibold text-sky-700 hover:bg-sky-100">Detail</a></td>
						</tr>
					@empty
						<tr><td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data PA yang sesuai filter.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		@if ($paOrders->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">{{ $paOrders->links() }}</div>
		@endif
	</div>
@endsection
