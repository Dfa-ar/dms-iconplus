@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Manajemen User';
	$subtitle = 'Akun akses sistem dan role per user';
@endphp

@section('title', 'Manajemen User')

@section('content')
	<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
		<div>
			<h2 class="text-lg font-bold text-slate-900">Daftar User</h2>
			<p class="text-sm text-slate-500">Data akun login untuk admin, petugas, supervisor, dan super admin.</p>
		</div>

		<a href="{{ route('system.users.create') }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Tambah User</a>
	</div>

	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Nama</th>
						<th class="px-5 py-3 font-medium">Email</th>
						<th class="px-5 py-3 font-medium">Role</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium text-right">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($users as $user)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4 font-medium text-slate-700">{{ $user->name }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $user->email }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $user->role?->name ?? '-' }}</td>
							<td class="px-5 py-4">
								@if ($user->is_active)
									<span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-medium text-emerald-700">Aktif</span>
								@else
									<span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600">Nonaktif</span>
								@endif
							</td>
							<td class="px-5 py-4">
								<div class="flex justify-end gap-2">
									<a href="{{ route('system.users.edit', $user) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Edit</a>

									@if ($user->id !== auth()->id())
										<form method="POST" action="{{ route('system.users.destroy', $user) }}" onsubmit="return confirm('Nonaktifkan akun ini?')">
											@csrf
											@method('DELETE')
											<button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Nonaktifkan</button>
										</form>
									@endif
								</div>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada user yang dibuat.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if ($users->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">
				{{ $users->links() }}
			</div>
		@endif
	</div>
@endsection
