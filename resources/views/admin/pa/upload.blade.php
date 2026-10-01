@extends('layouts.admin')

@php
	$active = 'pa';
	$title = 'Upload Data PA';
	$subtitle = 'Impor data Excel atau CSV untuk diproses menjadi PA baru';
@endphp

@section('title', 'Upload Data PA')

@section('content')
	<div class="max-w-3xl">
		<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
			<a href="{{ route('admin.pa.index') }}" class="text-sm font-medium text-sky-600">&larr; Kembali ke Master Data PA</a>
			<a href="{{ route('admin.pa.upload-history') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Riwayat upload</a>
		</div>
		<section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
			<h2 class="text-lg font-bold text-slate-900">Impor Excel / CSV</h2>
			<p class="mt-1 text-sm text-slate-500">Gunakan baris pertama sebagai header. Nomor PA yang sudah terdaftar ditolak dan dicatat dalam laporan error.</p>

			<div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
				<div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold text-slate-800">Kolom yang didukung</p><a href="{{ route('admin.pa.upload.template') }}" class="font-medium text-sky-600 hover:text-sky-800">Unduh template CSV</a></div>
				<p class="mt-1 break-words font-mono text-xs">pa_number, customer_id, id_pln, customer_name, contact_phone, address, kabupaten_kota, kecamatan, kelurahan, pa_date</p>
				<p class="mt-2 text-xs text-slate-500">Format tanggal: YYYY-MM-DD. Ukuran maksimal: 10 MB.</p>
			</div>

			<form method="POST" action="{{ route('admin.pa.upload') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
				@csrf
				<label class="block"><span class="mb-1 block text-sm font-medium text-slate-700">File data PA</span><input type="file" name="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-sky-700"></label>
				<div class="flex flex-wrap gap-2"><button formaction="{{ route('admin.pa.upload.preview') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview &amp; Cek Kolom</button><button class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">Mulai Import</button></div>
			</form>

			@isset($mapping)
				<div class="mt-6 border-t border-slate-100 pt-5"><h3 class="font-semibold text-slate-900">Hasil Mapping Header</h3><div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">@foreach ($requiredColumns as $column)<div class="rounded-lg border px-3 py-2 text-sm {{ array_key_exists($column, $mapping) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}"><span class="font-medium">{{ $column }}</span><span class="float-right">{{ array_key_exists($column, $mapping) ? $previewHeaders[$mapping[$column]] : 'Tidak ditemukan' }}</span></div>@endforeach</div></div>
				<div class="mt-5 overflow-x-auto"><h3 class="mb-2 font-semibold text-slate-900">Preview 10 Baris Pertama</h3><table class="w-full text-left text-xs"><thead><tr class="border-b border-slate-200">@foreach ($previewHeaders as $header)<th class="px-2 py-2 font-medium text-slate-500">{{ $header ?: '(kosong)' }}</th>@endforeach</tr></thead><tbody>@foreach ($previewRows as $row)<tr class="border-b border-slate-100">@foreach ($previewHeaders as $index => $header)<td class="px-2 py-2 text-slate-600">{{ $row[$index] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
			@endisset
		</section>
	</div>
@endsection
