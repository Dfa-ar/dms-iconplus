@extends('layouts.admin')

@php
    $active = 'pa';
    $title = 'Detail PA '.$paOrder->pa_number;
    $subtitle = 'Informasi pelanggan, penugasan, bukti, dan riwayat status';
@endphp

@section('title', 'Detail PA')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.pa.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-800">&larr; Kembali ke Master Data PA</a>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <section class="page-panel p-5 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <span class="page-kicker">PA detail</span>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">{{ $paOrder->pa_number }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $paOrder->customer_name }} · {{ $paOrder->customer_id }}</p>
                </div>
                <x-badge-status :status="$paOrder->current_status" />
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Telepon</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->contact_phone ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Tanggal PA</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->pa_date?->translatedFormat('d F Y') ?? '-' }}</dd></div>
                <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Alamat</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->address ?: '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Wilayah</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Kecamatan / Kelurahan</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->region?->kecamatan ?? '-' }} / {{ $paOrder->region?->kelurahan ?? '-' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Petugas</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->currentOfficer?->name ?? 'Belum ditugaskan' }}</dd></div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><dt class="text-slate-400">Aging</dt><dd class="mt-1 font-semibold text-slate-700">{{ $paOrder->aging }} hari</dd></div>
            </dl>

            @can('update', $paOrder)
                <details class="mt-6 border-t border-slate-100 pt-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">Koreksi status oleh Admin</summary>
                    <form method="POST" action="{{ route('admin.pa.correct', $paOrder) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf @method('PATCH')
                        <select name="current_status" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                            <option value="UNASSIGNED">UNASSIGNED</option>
                            <option value="ASSIGNED">ASSIGNED</option>
                            <option value="ON_PROGRESS">ON_PROGRESS</option>
                            <option value="DONE">DONE</option>
                            <option value="KENDALA">KENDALA</option>
                        </select>
                        <input name="correction_reason" required placeholder="Alasan koreksi" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                        <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 sm:col-span-2">Simpan Koreksi</button>
                    </form>
                </details>
            @endcan
        </section>

        <section class="page-panel p-5">
            <h3 class="text-base font-bold text-slate-900">Riwayat Status</h3>
            <div class="mt-4 space-y-4">
                @forelse ($paOrder->statusLogs->sortByDesc('changed_at') as $log)
                    <div class="border-l-2 border-sky-200 pl-3">
                        <p class="text-sm font-semibold text-slate-700">{{ $log->to_status }}</p>
                        <p class="text-xs text-slate-400">{{ $log->changed_at?->translatedFormat('d F Y H:i') }} · {{ $log->changedBy?->name ?? '-' }}</p>
                        @if ($log->note)
                            <p class="mt-1 text-xs text-slate-500">{{ $log->note }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Belum ada riwayat status.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection