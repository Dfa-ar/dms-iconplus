@extends('layouts.admin')

@php
    $active = 'settings';
    $title = 'Pengaturan';
    $subtitle = 'SLA operasional dan format nomor BAST';
@endphp

@section('title', 'Pengaturan')

@section('content')
<div class="max-w-4xl space-y-5">
    <div>
        <span class="page-kicker">Konfigurasi</span>
        <h1 class="mt-3 text-2xl font-extrabold text-slate-900">Pengaturan Operasional</h1>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="page-panel space-y-6 p-5">
        @csrf
        @method('PUT')
        <section>
            <h2 class="text-sm font-bold text-slate-900">SLA dan aging</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-semibold text-slate-600">SLA (hari)<input type="number" name="sla_days" min="1" max="365" value="{{ old('sla_days', $settings->sla_days) }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-xs font-semibold text-slate-600">Batas normal<input type="number" name="aging_green_max" min="0" max="365" value="{{ old('aging_green_max', $settings->aging_green_max) }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-xs font-semibold text-slate-600">Batas perhatian<input type="number" name="aging_yellow_max" min="1" max="365" value="{{ old('aging_yellow_max', $settings->aging_yellow_max) }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-xs font-semibold text-slate-600">Batas tinggi<input type="number" name="aging_orange_max" min="2" max="365" value="{{ old('aging_orange_max', $settings->aging_orange_max) }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-5">
            <h2 class="text-sm font-bold text-slate-900">Pola nomor BAST</h2>
            <label class="mt-3 block text-xs font-semibold text-slate-600">Format<input name="bast_number_format" value="{{ old('bast_number_format', $settings->bast_number_format) }}" required maxlength="100" pattern="[A-Za-z0-9_{}\/-]+" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"></label>
            <p class="mt-2 text-xs text-slate-500">Token wajib: <code>{sequence}</code>, <code>{kp_code}</code>, <code>{year}</code>.</p>
            <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">Contoh: {{ str_replace(['{sequence}', '{kp_code}', '{year}'], ['0001', 'DUMMY-BDG', now()->year], old('bast_number_format', $settings->bast_number_format)) }}</p>
        </section>

        <div class="flex justify-end border-t border-slate-100 pt-4"><button type="submit" class="rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Simpan pengaturan</button></div>
    </form>
</div>
@endsection