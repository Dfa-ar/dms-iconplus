@extends('layouts.admin')

@php
    $active = 'reports';
    $title = 'Laporan Excel';
    $subtitle = 'Ekspor data operasional per periode dan Kantor Perwakilan';
@endphp

@section('title', 'Laporan Excel')

@section('content')
<div class="max-w-3xl space-y-5">
    <div>
        <span class="page-kicker">Pelaporan</span>
        <h1 class="mt-3 text-2xl font-extrabold text-slate-900">Laporan Excel</h1>
        <p class="mt-1 text-sm text-slate-500">Pilih dataset, periode, dan KP untuk mengunduh workbook .xlsx.</p>
    </div>

    <form method="GET" action="{{ route('admin.reports.xlsx') }}" class="page-panel space-y-4 p-5">
        <label class="block text-sm font-semibold text-slate-700">Jenis laporan
            <select name="report_type" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="">Pilih laporan</option>
                @foreach($reports as $key => $label)
                    <option value="{{ $key }}" @selected(request('report_type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="text-xs font-semibold text-slate-600">Dari tanggal<input type="date" name="from_date" value="{{ request('from_date') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Sampai tanggal<input type="date" name="to_date" value="{{ request('to_date') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-xs font-semibold text-slate-600">Kantor Perwakilan<select name="kantor_perwakilan_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">Semua KP</option>@foreach($kantorPerwakilan as $office)<option value="{{ $office->id }}" @selected((string) request('kantor_perwakilan_id') === (string) $office->id)>{{ $office->kode }} · {{ $office->nama }}</option>@endforeach</select></label>
        </div>
        <div class="flex justify-end"><button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800"><x-dashboard-icon name="export" />Unduh XLSX</button></div>
    </form>
</div>
@endsection