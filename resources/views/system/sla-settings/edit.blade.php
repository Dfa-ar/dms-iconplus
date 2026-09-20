@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Pengaturan SLA';
	$subtitle = 'Threshold aging & prioritas layanan';
@endphp

@section('title', 'Pengaturan SLA')

@section('content')
	<div class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
		<div class="mb-6">
			<h2 class="text-lg font-bold text-slate-900">Atur SLA & Aging</h2>
			<p class="text-sm text-slate-500">Nilai ini dipakai untuk menentukan prioritas dan status over SLA pada dashboard dan laporan.</p>
		</div>

		<form method="POST" action="{{ route('system.sla-settings.update') }}" class="space-y-5">
			@csrf
			@method('PATCH')

			<div class="grid gap-5 md:grid-cols-2">
				<div>
					<label class="mb-1 block text-sm font-medium text-slate-700">SLA Hari</label>
					<input type="number" name="sla_days" value="{{ old('sla_days', $sla->sla_days) }}" min="1" max="365" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-slate-700">Green Max</label>
					<input type="number" name="aging_green_max" value="{{ old('aging_green_max', $sla->aging_green_max) }}" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-slate-700">Yellow Max</label>
					<input type="number" name="aging_yellow_max" value="{{ old('aging_yellow_max', $sla->aging_yellow_max) }}" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-slate-700">Orange Max</label>
					<input type="number" name="aging_orange_max" value="{{ old('aging_orange_max', $sla->aging_orange_max) }}" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
				</div>
			</div>

			<div class="flex justify-end">
				<button type="submit" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Simpan Pengaturan</button>
			</div>
		</form>
	</div>
@endsection
