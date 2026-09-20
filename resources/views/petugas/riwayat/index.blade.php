@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Riwayat Pekerjaan';
	$subtitle = 'Log penyelesaian dan kendala tugas';
@endphp

@section('title', 'Riwayat Pekerjaan')

@section('content')
	<div class="rounded-xl border border-slate-200 bg-white">
		<div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
			<div>
				<h2 class="text-lg font-bold text-slate-900">Riwayat Tugas</h2>
				<p class="text-sm text-slate-500">Daftar PA yang sudah selesai atau dengan kendala.</p>
			</div>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full text-left text-sm">
				<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
					<tr>
						<th class="px-5 py-3 font-medium">PA</th>
						<th class="px-5 py-3 font-medium">Pelanggan</th>
						<th class="px-5 py-3 font-medium">Status</th>
						<th class="px-5 py-3 font-medium">Tanggal</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100">
					@forelse ($history as $task)
						<tr class="hover:bg-slate-50/60">
							<td class="px-5 py-4 font-medium text-slate-700">{{ $task->pa_number }}</td>
							<td class="px-5 py-4 text-slate-500">{{ $task->customer_name }}</td>
							<td class="px-5 py-4"><x-badge-status :status="$task->current_status" /></td>
							<td class="px-5 py-4 text-slate-500">{{ $task->completed_at?->translatedFormat('d F Y') ?? '-' }}</td>
						</tr>
					@empty
						<tr>
							<td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada riwayat pekerjaan.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if (method_exists($history, 'hasPages') && $history->hasPages())
			<div class="border-t border-slate-100 px-5 py-3">
				{{ $history->links() }}
			</div>
		@endif
	</div>
@endsection
