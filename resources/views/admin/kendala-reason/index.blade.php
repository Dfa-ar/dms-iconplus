@extends('layouts.admin')

@php
	$active = 'dashboard';
	$title = 'Alasan Kendala';
	$subtitle = 'Kontrol kode alasan hambatan aktif dan nonaktif';
@endphp

@section('title', 'Alasan Kendala')

@section('content')
	<div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
		<div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
			<div>
				<h2 class="text-lg font-bold text-slate-900">Daftar Alasan Kendala</h2>
				<p class="text-sm text-slate-500">Kelola kategori masalah yang sering dilaporkan petugas saat pekerjaan berjalan.</p>
			</div>

			<form method="POST" action="{{ route('admin.kendala-reasons.store') }}" class="flex w-full max-w-lg flex-col gap-2 sm:flex-row">
				@csrf
				<input type="text" name="label" value="{{ old('label') }}" placeholder="Masukkan alasan kendala baru" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Tambah</button>
			</form>
		</div>
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Alasan</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($reasons as $reason)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4 font-medium text-slate-700">{{ $reason->label }}</td>
							<td class="px-5 py-4">
								@if ($reason->is_active)
									<span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-medium text-emerald-700">Aktif</span>
								@else
									<span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600">Nonaktif</span>
								@endif
							</td>
							<td class="px-5 py-4">
								<div class="flex justify-end gap-2">
									<form method="POST" action="{{ route('admin.kendala-reasons.update', $reason) }}">
										@csrf
										@method('PATCH')
										<input type="hidden" name="is_active" value="{{ $reason->is_active ? 0 : 1 }}">
										<button type="submit" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
											{{ $reason->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
										</button>
									</form>
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="3" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada alasan kendala yang ditambahkan.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
@endsection
