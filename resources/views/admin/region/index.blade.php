@extends('layouts.admin')

@php
	$active = 'dashboard';
	$title = 'Master Wilayah';
	$subtitle = 'Pengelompokan wilayah dan regional';
@endphp

@section('title', 'Master Wilayah')

@section('content')
	<div class="space-y-5">
		<div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
			<div>
				<span class="page-kicker">Master data</span>
				<h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Daftar Wilayah</h2>
				<p class="mt-1 text-sm text-slate-500">Kelola wilayah operasional untuk grouping PA dan petugas.</p>
			</div>
		</div>

		<div class="grid gap-3 sm:grid-cols-3">
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total wilayah</p>
				<p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $regions->total() }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Kabupaten/kota</p>
				<p class="mt-2 text-2xl font-extrabold text-sky-600">{{ $regions->pluck('kabupaten_kota')->unique()->count() }}</p>
			</div>
			<div class="page-panel p-4">
				<p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Group</p>
				<p class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $regions->pluck('parent_group')->filter()->unique()->count() }}</p>
			</div>
		</div>

		<div class="page-panel p-5">
			<form method="POST" action="{{ route('admin.regions.store') }}" class="grid w-full gap-3 md:grid-cols-4">
				@csrf
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kabupaten/Kota</label>
					<input type="text" name="kabupaten_kota" placeholder="Contoh: Tasikmalaya" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kecamatan</label>
					<input type="text" name="kecamatan" placeholder="Contoh: Cipedes" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kelurahan</label>
					<input type="text" name="kelurahan" placeholder="Contoh: Indihiang" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kode KP</label>
					<input type="text" name="office_code" placeholder="Contoh: KP-TSM" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="md:col-span-2">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Nama Kantor Perwakilan</label>
					<input type="text" name="office_name" placeholder="Contoh: Kantor Perwakilan Tasikmalaya" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Level</label>
					<select name="level" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
						<option value="">Pilih level</option>
						<option value="office">Kantor</option>
						<option value="kabupaten_kota">Kabupaten/Kota</option>
						<option value="kecamatan">Kecamatan</option>
						<option value="kelurahan">Kelurahan</option>
					</select>
				</div>
				<div class="md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Group utama</label>
					<input type="text" name="parent_group" placeholder="Contoh: Tasikmalaya" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
				</div>
				<div class="flex items-end md:col-span-1">
					<label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Apakah kantor?</label>
					<label class="flex h-[42px] items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
						<input type="checkbox" name="is_office" value="1" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
						<span>Kantor</span>
					</label>
				</div>
				<div class="flex items-end md:col-span-1">
					<button type="submit" class="w-full rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Tambah wilayah</button>
				</div>
			</form>
		</div>

		<div class="page-panel overflow-hidden">
			<div class="overflow-x-auto">
				<table class="w-full text-left text-sm">
					<thead class="bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
						<tr>
							<th class="px-5 py-3 font-medium">Wilayah</th>
							<th class="px-5 py-3 font-medium">Office / Group</th>
							<th class="px-5 py-3 font-medium text-right">Aksi</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-slate-100">
						@forelse ($regions as $region)
							<tr class="hover:bg-slate-50/60">
								<td class="px-5 py-4">
									<p class="font-semibold text-slate-800">{{ $region->kabupaten_kota }}</p>
									<p class="text-xs text-slate-400">{{ $region->kecamatan ?? '-' }} / {{ $region->kelurahan ?? '-' }}</p>
									@if ($region->office_code || $region->office_name)
										<p class="mt-1 text-[11px] text-sky-600">{{ $region->office_code ?? 'KP' }} · {{ $region->office_name ?? 'Kantor Perwakilan' }}</p>
									@endif
								</td>
								<td class="px-5 py-4 text-slate-500">
									<div class="flex flex-col gap-1">
										<span>{{ $region->office_name ?? $region->parent_group ?? '-' }}</span>
										@if ($region->level)
											<span class="text-[11px] uppercase tracking-[0.12em] text-slate-400">{{ $region->level }}</span>
										@endif
									</div>
								</td>
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
	</div>
@endsection
