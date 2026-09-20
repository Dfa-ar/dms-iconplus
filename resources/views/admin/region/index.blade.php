@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Master Wilayah';
	$subtitle = 'Pengelompokan wilayah dan regional';
@endphp

@section('title', 'Master Wilayah')

@section('content')
	<div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
		<div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
			<div>
				<h2 class="text-lg font-bold text-slate-900">Daftar Wilayah</h2>
				<p class="text-sm text-slate-500">Kelola wilayah operasional untuk grouping PA dan petugas.</p>
			</div>

			<form method="POST" action="{{ route('admin.regions.store') }}" class="grid w-full max-w-2xl gap-2 md:grid-cols-3">
				@csrf
				<input type="text" name="kabupaten_kota" placeholder="Kabupaten/Kota" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<input type="text" name="kecamatan" placeholder="Kecamatan" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<input type="text" name="kelurahan" placeholder="Kelurahan" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<input type="text" name="parent_group" placeholder="Group utama" class="md:col-span-2 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Tambah</button>
			</form>
		</div>
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Wilayah</th>
						<th class="px-5 py-3 font-medium">Group</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($regions as $region)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4">
								<p class="font-medium text-slate-700">{{ $region->kabupaten_kota }}</p>
								<p class="text-xs text-slate-400">{{ $region->kecamatan ?? '-' }} / {{ $region->kelurahan ?? '-' }}</p>
							</td>
							<td class="px-5 py-4 text-slate-500">{{ $region->parent_group ?? '-' }}</td>
							<td class="px-5 py-4">
								<div class="flex justify-end">
									<form method="POST" action="{{ route('admin.regions.destroy', $region) }}" onsubmit="return confirm('Hapus wilayah ini?')">
										@csrf
										@method('DELETE')
										<button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Hapus</button>
									</form>
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="3" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada wilayah terdaftar.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if ($regions->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">
				{{ $regions->links() }}
			</div>
		@endif
	</div>
@endsection
