@extends('layouts.app')

@php
	$active = 'dashboard';
	$title = 'Detail PA';
	$subtitle = $paOrder->pa_number;
@endphp

@section('title', 'Detail PA')

@section('content')
	<div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
		<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div>
				<h2 class="text-lg font-bold text-slate-900">{{ $paOrder->pa_number }}</h2>
				<p class="text-sm text-slate-500">{{ $paOrder->customer_name }}</p>
			</div>

			<x-badge-status :status="$paOrder->current_status" />
		</div>

		<div class="mt-6 grid gap-5 md:grid-cols-2">
			<div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
				<p class="text-xs uppercase tracking-wide text-slate-400">Informasi</p>
				<dl class="mt-3 space-y-2 text-sm">
					<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Wilayah</dt><dd class="font-medium text-slate-700">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</dd></div>
					<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Alamat</dt><dd class="text-right font-medium text-slate-700">{{ $paOrder->address ?? '-' }}</dd></div>
					<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">NOC</dt><dd class="font-medium text-slate-700">{{ $paOrder->noc_number ?? '-' }}</dd></div>
					<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Tanggal PA</dt><dd class="font-medium text-slate-700">{{ $paOrder->pa_date?->translatedFormat('d M Y') ?? '-' }}</dd></div>
				</dl>
			</div>

			<div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
				<p class="text-xs uppercase tracking-wide text-slate-400">Aksi</p>

				<div class="mt-4 flex flex-wrap gap-2">
					@can('start', $paOrder)
						<form method="POST" action="{{ route('petugas.tasks.start', $paOrder) }}">
							@csrf
							<button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Mulai</button>
						</form>
					@endcan

					@can('complete', $paOrder)
						<details class="w-full rounded-lg border border-emerald-200 bg-emerald-50 p-3"><summary class="cursor-pointer text-sm font-semibold text-emerald-700">Tandai Selesai</summary><form method="POST" action="{{ route('petugas.tasks.complete', $paOrder) }}" enctype="multipart/form-data" class="mt-3 grid gap-3"><div class="grid gap-3 sm:grid-cols-2"><input name="receiver_name" required placeholder="Nama penerima" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="datetime-local" name="pickup_time" value="{{ now()->format('Y-m-d\\TH:i') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></div><textarea name="notes" rows="2" placeholder="Catatan (opsional)" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea><div class="grid gap-2 sm:grid-cols-3"><input type="file" name="foto_perangkat" accept="image/*" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"><input type="file" name="foto_modem_ont" accept="image/*" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"><input type="file" name="foto_serah_terima" accept="image/*" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"></div>@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Simpan Selesai</button></form></details>
					@endcan

					@can('reportKendala', $paOrder)
						<details class="w-full rounded-lg border border-amber-200 bg-amber-50 p-3"><summary class="cursor-pointer text-sm font-semibold text-amber-700">Laporkan Kendala</summary><form method="POST" action="{{ route('petugas.tasks.kendala', $paOrder) }}" enctype="multipart/form-data" class="mt-3 grid gap-3">@csrf<select name="kendala_reason_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Pilih alasan kendala</option>@foreach ($kendalaReasons as $reason)<option value="{{ $reason->id }}">{{ $reason->label }}</option>@endforeach</select><textarea name="notes" required rows="3" placeholder="Keterangan kendala" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea><input type="file" name="foto_kendala" accept="image/*" class="rounded-lg border border-slate-200 px-2 py-2 text-xs"><button class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white">Simpan Kendala</button></form></details>
					@endcan
				</div>
			</div>
		</div>
	</div>
@endsection
