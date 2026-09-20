@extends('layouts.app')

@php
	$active = 'petugas';
	$title = 'Master Petugas';
	$subtitle = 'Daftar petugas aktif dan target harian';
@endphp

@section('title', 'Master Petugas')

@section('content')
	<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
		<div>
			<h2 class="text-lg font-bold text-slate-900">Petugas Operasional</h2>
			<p class="text-sm text-slate-500">Pantau pemetaan petugas per wilayah dan kapasitas harian.</p>
		</div>

		<a href="{{ route('admin.petugas.create') }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Tambah Petugas</a>
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Nama</th>
						<th class="px-5 py-3 font-medium">Wilayah</th>
						<th class="px-5 py-3 font-medium">NIP</th>
						<th class="px-5 py-3 font-medium">Target Hari Ini</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($officers as $officer)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4">
								<p class="font-medium text-slate-700">{{ $officer->name }}</p>
								<p class="text-xs text-slate-400">{{ $officer->user?->email ?? '-' }}</p>
							</td>
							<td class="px-5 py-4 text-slate-500">{{ $officer->region?->kabupaten_kota ?? '-' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $officer->employee_code }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $officer->daily_target }}</td>
							<td class="px-5 py-4">
								@if ($officer->is_active)
									<span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-medium text-emerald-700">Aktif</span>
								@else
									<span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600">Nonaktif</span>
								@endif
							</td>
							<td class="px-5 py-4">
								<div class="flex justify-end gap-2">
									<a href="{{ route('admin.petugas.edit', $officer) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>

									<form method="POST" action="{{ route('admin.petugas.destroy', $officer) }}" onsubmit="return confirm('Nonaktifkan petugas ini?')">
										@csrf
										@method('DELETE')
										<button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Nonaktifkan</button>
									</form>
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data petugas.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if ($officers->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">
				{{ $officers->links() }}
			</div>
		@endif
	</div>
@endsection
