@extends('layouts.petugas')

@php
	$active = 'dashboard';
	$title = 'Detail PA';
	$subtitle = $paOrder->pa_number;
@endphp

@section('title', 'Detail PA')

@section('content')
	<div class="mx-auto max-w-4xl space-y-5">
		<section class="page-panel p-5 sm:p-6">
			<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
				<div>
					<span class="page-kicker">PA aktif</span>
					<h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">{{ $paOrder->pa_number }}</h2>
					<p class="mt-1 text-sm text-slate-500">{{ $paOrder->customer_name }}</p>
				</div>
				<x-badge-status :status="$paOrder->current_status" />
			</div>

			<div class="mt-5 grid gap-4 md:grid-cols-2">
				<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
					<p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Informasi</p>
					<dl class="mt-3 space-y-3 text-sm">
						<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Wilayah</dt><dd class="font-semibold text-slate-700">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</dd></div>
						<div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Alamat</dt><dd class="max-w-[60%] text-right font-semibold text-slate-700">{{ $paOrder->address ?? '-' }}</dd></div>
						<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">NOC</dt><dd class="font-semibold text-slate-700">{{ $paOrder->noc_number ?? '-' }}</dd></div>
						<div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Tanggal PA</dt><dd class="font-semibold text-slate-700">{{ $paOrder->pa_date?->translatedFormat('d M Y') ?? '-' }}</dd></div>
					</dl>
				</div>

				<div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
					<p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-700">Aksi operasional</p>
					<div class="mt-4 flex flex-wrap gap-2">
						@can('start', $paOrder)
							<form method="POST" action="{{ route('petugas.tasks.start', $paOrder) }}">
								@csrf
								<button type="submit" class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Mulai</button>
							</form>
						@endcan

						@can('complete', $paOrder)
							<a href="#close-icrm" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Lanjutkan checklist</a>
						@endcan

						@can('reportKendala', $paOrder)
							<details class="w-full rounded-xl border border-amber-200 bg-white p-3">
								<summary class="cursor-pointer text-sm font-semibold text-amber-700">Laporkan Kendala</summary>
								<form method="POST" action="{{ route('petugas.tasks.kendala', $paOrder) }}" enctype="multipart/form-data" class="mt-3 grid gap-3">
									@csrf
									<select name="kendala_reason_id" required class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">Pilih alasan kendala</option>@foreach ($kendalaReasons as $reason)<option value="{{ $reason->id }}">{{ $reason->label }}</option>@endforeach</select>
									<textarea name="notes" required rows="3" placeholder="Keterangan kendala" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></textarea>
									<input type="file" name="foto_kendala" accept="image/*" class="rounded-xl border border-slate-200 px-2 py-2 text-xs">
									<button class="rounded-xl bg-amber-500 px-3 py-2.5 text-sm font-semibold text-white hover:bg-amber-600">Simpan Kendala</button>
								</form>
							</details>
						@endcan
					</div>
				</div>
			</div>
		</section>

		@can('complete', $paOrder)
			@php
				$savedStep = (int) str_replace('STEP_', '', (string) $paOrder->close_icrm_step);
				$currentStep = min($savedStep + 1, 6);
				$stepLabels = [
					1 => 'K3 Safety First',
					2 => 'Dismantle — ONT',
					3 => 'Dismantle — Kabel',
					4 => 'Konfirmasi ID PLN',
					5 => 'K3 Safety Final',
					6 => 'BA Pengambilan Perangkat',
				];
				$evidenceByType = $paOrder->evidences->keyBy('type');
			@endphp
			<section id="close-icrm" class="page-panel p-5 sm:p-6">
				<div class="flex flex-wrap items-end justify-between gap-3">
					<div>
						<span class="page-kicker">Close ICRM</span>
						<h3 class="mt-2 text-xl font-bold text-slate-900">Langkah {{ $currentStep }} dari 6</h3>
					</div>
					<span class="text-sm font-semibold text-slate-500">{{ $paOrder->close_icrm_step ?? 'Belum dimulai' }}</span>
				</div>

				<ol class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
					@foreach ($stepLabels as $stepNo => $stepLabel)
						<li class="flex min-h-14 items-center gap-3 rounded-xl border px-3 py-2 {{ $stepNo <= $savedStep ? 'border-emerald-200 bg-emerald-50' : ($stepNo === $currentStep ? 'border-sky-300 bg-sky-50' : 'border-slate-200 bg-slate-50') }}">
							<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $stepNo <= $savedStep ? 'bg-emerald-600 text-white' : ($stepNo === $currentStep ? 'bg-sky-600 text-white' : 'bg-slate-200 text-slate-600') }}">{{ $stepNo <= $savedStep ? '✓' : $stepNo }}</span>
							<span class="text-sm font-semibold {{ $stepNo <= $savedStep ? 'text-emerald-800' : 'text-slate-700' }}">{{ $stepLabel }}</span>
						</li>
					@endforeach
				</ol>

				@if ($currentStep <= 6)
					<form method="POST" action="{{ route('petugas.tasks.close-icrm', $paOrder) }}" enctype="multipart/form-data" class="mt-6 space-y-5">
						@csrf
						<input type="hidden" name="step_number" value="{{ $currentStep }}">

						@if ($currentStep === 1)
							<div>
								<label for="foto_k3_awal" class="mb-1 block text-sm font-semibold text-slate-800">Foto APD lengkap sebelum bekerja</label>
								<p class="mb-2 text-xs text-slate-500">Pastikan helm, rompi, dan sepatu terlihat jelas.</p>
								<input id="foto_k3_awal" type="file" name="foto_k3_awal" accept="image/*" capture="environment" required class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
							</div>
						@elseif ($currentStep === 2)
							<div class="grid gap-4 sm:grid-cols-2">
								<label class="block text-sm font-semibold text-slate-800">Foto ONT tampak depan<input type="file" name="foto_ont_depan" accept="image/*" capture="environment" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
								<label class="block text-sm font-semibold text-slate-800">Foto belakang ONT dengan SN terlihat<input type="file" name="foto_ont_belakang_sn" accept="image/*" capture="environment" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
							</div>
							<label class="block text-sm font-semibold text-slate-800">Serial Number ONT (opsional jika tidak terbaca)<input name="serial_number_ont" value="{{ old('serial_number_ont', $paOrder->serial_number_ont) }}" maxlength="120" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
							<p class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Keterbacaan SN diperiksa QC dari foto belakang ONT. Nilai ketikan tidak menggantikan bukti foto.</p>
						@elseif ($currentStep === 3)
							<label class="block text-sm font-semibold text-slate-800">Panjang kabel (meter)<input type="number" name="kabel_panjang_meter" min="0" step="0.01" value="{{ old('kabel_panjang_meter', $paOrder->kabel_panjang_meter ?? 0) }}" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
							<label class="block text-sm font-semibold text-slate-800">Foto FAT terdekat<input type="file" name="foto_fat_terdekat" accept="image/*" capture="environment" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
							<div class="grid gap-4 sm:grid-cols-3">
								<label class="block text-sm font-semibold text-slate-800">FAT<select id="fat-point-select" name="fat_point_id" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Pilih FAT</option>@foreach ($fatPoints as $fatPoint)<option value="{{ $fatPoint->id }}" @selected((string) old('fat_point_id', $paOrder->fat_point_id) === (string) $fatPoint->id)>{{ $fatPoint->kode_fat }} — {{ $fatPoint->nama_fat }}</option>@endforeach</select></label>
								<label class="block text-sm font-semibold text-slate-800">Splitter<select id="splitter-select" name="splitter_id" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Pilih splitter</option>@foreach ($fatPoints as $fatPoint)@foreach ($fatPoint->splitters as $splitter)<option value="{{ $splitter->id }}" data-fat="{{ $fatPoint->id }}" @selected((string) old('splitter_id', $paOrder->splitter_id) === (string) $splitter->id)>{{ $splitter->kode_splitter }}</option>@endforeach @endforeach</select></label>
								<label class="block text-sm font-semibold text-slate-800">Port<select id="port-select" name="port_number" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Pilih port tersedia</option>@foreach ($fatPoints as $fatPoint)@foreach ($fatPoint->splitters as $splitter)@php $usedPorts = $splitter->paOrders->pluck('port_number')->map(fn ($port) => (int) $port)->all(); @endphp@for ($port = 1; $port <= $splitter->capacity_port; $port++)<option value="{{ $port }}" data-splitter="{{ $splitter->id }}" @disabled(in_array($port, $usedPorts, true)) @selected((string) old('port_number', $paOrder->port_number) === (string) $port && (string) old('splitter_id', $paOrder->splitter_id) === (string) $splitter->id)>{{ $splitter->kode_splitter }} / Port {{ $port }}{{ in_array($port, $usedPorts, true) ? ' (terpakai)' : '' }}</option>@endfor @endforeach @endforeach</select></label>
							</div>
						@elseif ($currentStep === 4)
							<div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
								<p class="text-xs font-semibold uppercase text-slate-500">ID PLN dari Plan Harian</p>
								<p class="mt-1 font-mono text-lg font-bold text-slate-900">{{ $paOrder->id_pln ?: 'Belum tersedia' }}</p>
							</div>
							<label class="flex items-start gap-3 text-sm font-medium text-slate-800"><input type="checkbox" name="id_pln_confirmed" value="1" required class="mt-1"> Saya memastikan ID PLN di atas sesuai dengan pelanggan.</label>
							<label class="block text-sm font-semibold text-slate-800">Status KWH Meter<select id="kwh-status-select" name="kwh_status" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Pilih status</option><option value="ADA" @selected(old('kwh_status', $paOrder->kwh_status) === 'ADA')>Ada</option><option value="TIDAK_DITEMUKAN" @selected(old('kwh_status', $paOrder->kwh_status) === 'TIDAK_DITEMUKAN')>KWH tidak ditemukan</option></select></label>
							<label class="block text-sm font-semibold text-slate-800">Catatan KWH<textarea name="kwh_note" required maxlength="255" rows="2" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ old('kwh_note', $paOrder->kwh_note) }}</textarea></label>
							<div id="kwh-photo-field" class="hidden"><label class="block text-sm font-semibold text-slate-800">Foto KWH Meter<input type="file" name="foto_kwh_meter" accept="image/*" capture="environment" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label></div>
							<script>
								const kwhStatus = document.getElementById('kwh-status-select');
								const kwhPhotoField = document.getElementById('kwh-photo-field');
								const kwhPhoto = kwhPhotoField.querySelector('input[type="file"]');
								const updateKwhPhoto = () => {
									const isPresent = kwhStatus.value === 'ADA';
									kwhPhotoField.classList.toggle('hidden', !isPresent);
									kwhPhoto.required = isPresent;
								};
								kwhStatus.addEventListener('change', updateKwhPhoto);
								updateKwhPhoto();
							</script>
							<p class="text-xs text-slate-500">Foto KWH wajib jika statusnya Ada. Jika tidak ditemukan, isi catatan kondisi/lokasi pemeriksaan.</p>
						@elseif ($currentStep === 5)
							<label class="block text-sm font-semibold text-slate-800">Foto APD lengkap setelah pekerjaan<input type="file" name="foto_k3_akhir" accept="image/*" capture="environment" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
						@else
							<div class="grid gap-4 sm:grid-cols-2">
								<label class="block text-sm font-semibold text-slate-800">BA Pengambilan ditandatangani pelanggan<input type="file" name="ba_pengambilan_perangkat" accept=".pdf,image/*" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>
								<label class="block text-sm font-semibold text-slate-800">Nama penerima perangkat<input name="receiver_name" value="{{ old('receiver_name') }}" required maxlength="150" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
							</div>
							<p class="text-xs text-slate-500">Waktu pengambilan dicatat otomatis saat dokumen dikirim. BA ini terpisah dari BAST batch Admin.</p>
						@endif

						@if ($errors->any())
							<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
						@endif
						<div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
							<span class="text-xs text-slate-500">Bukti langkah wajib diperiksa sebelum melanjutkan.</span>
							<button type="submit" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">{{ $currentStep === 6 ? 'Kirim BA & Selesaikan PA' : 'Simpan & Lanjutkan' }}</button>
						</div>
					</form>
				@else
					<p class="mt-5 rounded-lg bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">Checklist lengkap dan BA Pengambilan tersimpan. PA menunggu pemeriksaan QC.</p>
				@endif
			</section>
		@endcan
	</div>
@endsection
