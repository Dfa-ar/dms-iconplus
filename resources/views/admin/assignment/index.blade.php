@extends('layouts.app')

@php
	$active = 'assignment';
	$title = 'Auto-Assignment';
	$subtitle = 'Pratinjau pembagian PA sebelum dikirim ke petugas';
@endphp

@section('title', 'Auto-Assignment')

@section('content')
	<div class="mb-6"><h2 class="text-lg font-bold text-slate-900">Generate Tugas Hari Ini</h2><p class="text-sm text-slate-500">Pilih wilayah dan target. Sistem memprioritaskan PA dengan tanggal paling lama.</p></div>
	<form method="GET" action="{{ route('admin.assignments.index') }}" class="mb-5 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-3">
		<select name="region_id" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Pilih kabupaten/kota</option>@foreach ($regions as $region)<option value="{{ $region->id }}" @selected($selectedRegion?->id === $region->id)>{{ $region->kabupaten_kota }}</option>@endforeach</select>
		<input type="number" name="target_per_officer" min="1" max="100" value="{{ $target }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Target per petugas">
		<button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Tampilkan Preview</button>
	</form>

	@if ($selectedRegion)
		<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
			<div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-400">Petugas aktif</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $officers->count() }}</p></div>
			<div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-400">Kandidat PA</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $candidates->count() }}</p></div>
			<div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-400">Kapasitas target</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $officers->count() * $target }}</p></div>
		</div>

		<div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
			<section class="rounded-xl border border-slate-200 bg-white p-5"><div class="mb-4 flex items-center justify-between"><div><h3 class="font-semibold text-slate-900">Petugas &amp; Kuota</h3><p class="text-xs text-slate-400">Target dikurangi pekerjaan yang masih berjalan.</p></div></div><div class="space-y-3">@forelse ($officers as $officer)<div class="rounded-lg bg-slate-50 px-3 py-3"><div class="flex items-center justify-between gap-3"><div><p class="text-sm font-medium text-slate-800">{{ $officer->name }}</p><p class="text-xs text-slate-400">{{ $officer->employee_code }} · carry-over {{ $officer->carry_over_count }}</p></div><span class="text-sm font-semibold text-sky-700">{{ max($target - $officer->carry_over_count, 0) }} slot</span></div><button type="button" data-copy-message="📢 Tugas Deaktivasi Hari Ini\nHalo {{ $officer->name }}, terdapat {{ max($target - $officer->carry_over_count, 0) }} PA yang perlu dikerjakan hari ini.\nSilakan akses tugas melalui sistem Deaktivasi ICONNET.\nTarget hari ini: {{ max($target - $officer->carry_over_count, 0) }} PA\nWilayah: {{ $selectedRegion->kabupaten_kota }}\nLink: {{ url('/my/tasks') }}" class="mt-3 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100">Salin pesan WhatsApp</button></div>@empty<p class="text-sm text-slate-400">Tidak ada petugas aktif di wilayah ini.</p>@endforelse</div></section>
			<section class="rounded-xl border border-slate-200 bg-white p-5"><div class="mb-4"><h3 class="font-semibold text-slate-900">Kandidat PA Tertua</h3><p class="text-xs text-slate-400">Menampilkan maksimal 100 kandidat untuk preview.</p></div><div class="max-h-80 space-y-2 overflow-y-auto">@forelse ($candidates as $pa)<div class="flex items-center justify-between border-b border-slate-100 pb-2 text-sm"><div><p class="font-medium text-slate-700">{{ $pa->pa_number }}</p><p class="text-xs text-slate-400">{{ $pa->region?->kecamatan ?? '-' }} · {{ $pa->pa_date?->format('d/m/Y') }}</p></div><span class="text-xs text-slate-400">{{ $pa->aging }} hari</span></div>@empty<p class="text-sm text-slate-400">Tidak ada PA UNASSIGNED di wilayah ini.</p>@endforelse</div></section>
		</div>

		<form method="POST" action="{{ route('admin.assignments.generate') }}" class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-5">@csrf<input type="hidden" name="region_id" value="{{ $selectedRegion->id }}"><input type="hidden" name="target_per_officer" value="{{ $target }}"><div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold text-sky-900">Konfirmasi pembagian</h3><p class="text-sm text-sky-700">{{ $candidates->count() }} kandidat akan diproses sesuai kuota petugas aktif.</p></div><button class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700" onclick="return confirm('Generate assignment untuk wilayah ini sekarang?')">Generate Tugas</button></div></form>
	@endif
@endsection

@push('scripts')
	<script>
		document.querySelectorAll('[data-copy-message]').forEach((button) => {
			button.addEventListener('click', async () => {
				await navigator.clipboard.writeText(button.dataset.copyMessage);
				const original = button.textContent;
				button.textContent = 'Pesan tersalin';
				window.setTimeout(() => button.textContent = original, 1500);
			});
		});
	</script>
@endpush
