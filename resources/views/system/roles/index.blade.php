@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Kelola Role';
	$subtitle = 'Manajemen peran pada sistem';
@endphp

@section('title', 'Kelola Role')

@section('content')
	<div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
		<div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
			<div>
				<h2 class="text-lg font-bold text-slate-900">Role User</h2>
				<p class="text-sm text-slate-500">Kelola daftar peran yang dapat dipilih saat pembuatan akun.</p>
			</div>

			<form method="POST" action="{{ route('system.roles.store') }}" class="flex w-full max-w-md gap-2">
				@csrf
				<input type="text" name="name" value="{{ old('name') }}" placeholder="Nama role baru" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				<button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Tambah</button>
			</form>
		</div>
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Nama Role</th>
						<th class="px-5 py-3 font-medium">Jumlah User</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($roles as $role)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4 font-medium text-slate-700">{{ $role->name }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $role->users_count }} user</td>
							<td class="px-5 py-4">
								<div class="flex justify-end">
									@if ($role->users_count === 0)
										<form method="POST" action="{{ route('system.roles.destroy', $role) }}" onsubmit="return confirm('Yakin ingin menghapus role ini?')">
											@csrf
											@method('DELETE')
											<button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Hapus</button>
										</form>
									@else
										<span class="text-xs text-slate-400">Dipakai</span>
									@endif
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="3" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada role yang dibuat.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
@endsection
