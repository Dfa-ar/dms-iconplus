@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Audit Log';
	$subtitle = 'Riwayat aktivitas sistem';
@endphp

@section('title', 'Audit Log')

@section('content')
	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">Waktu</th>
						<th class="px-5 py-3 font-medium">User</th>
						<th class="px-5 py-3 font-medium">Aksi</th>
						<th class="px-5 py-3 font-medium">Detail</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($logs as $log)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4 text-slate-500">{{ $log->created_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
							<td class="px-5 py-4 font-medium text-slate-700">{{ $log->user?->name ?? '-' }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $log->action }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $log->details ?? '-' }}</td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada log aktivitas.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if ($logs->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">
				{{ $logs->links() }}
			</div>
		@endif
	</div>
@endsection
