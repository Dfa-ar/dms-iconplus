@extends('layouts.petugas')

@php
    $active = 'report';
    $title = 'Laporan Kepatuhan';
    $subtitle = 'Ringkasan harian petugas';
@endphp

@section('title', 'Laporan Kepatuhan Harian')

@section('content')
    <div class="mx-auto w-full max-w-[440px] space-y-4 p-4 lg:mx-0 lg:max-w-none" data-reveal>
        <section class="rounded-[24px] bg-slate-900 p-5 text-white shadow-lg shadow-slate-200">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-300">Operasional harian</p>
            <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Laporan Kepatuhan Harian</h2>
            <p class="mt-1 text-sm text-slate-300">{{ now()->translatedFormat('l, d F Y') }}</p>
        </section>

        <div class="grid grid-cols-2 gap-3">
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Total PA</p>
                <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $summary['total_tasks'] }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Tingkat penyelesaian</p>
                <p class="mt-2 text-2xl font-extrabold text-emerald-600">{{ $summary['completion_rate'] }}%</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Menunggu QC</p>
                <p class="mt-2 text-2xl font-extrabold text-sky-600">{{ $summary['pending_qc'] }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Kendala</p>
                <p class="mt-2 text-2xl font-extrabold text-amber-600">{{ $summary['kendala_tasks'] }}</p>
            </div>
        </div>

        <section class="page-panel p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-extrabold text-slate-900">Indikator kepatuhan</h3>
                <a href="{{ route('petugas.tasks.index') }}" class="text-xs font-bold text-sky-600">Kembali ke tugas</a>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-500">
                        <span>Dokumentasi lengkap</span>
                        <span>{{ $summary['document_compliance'] }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-sky-600" style="width: {{ $summary['document_compliance'] }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-500">
                        <span>Checklist K3 selesai</span>
                        <span>{{ $summary['k3_compliance'] }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ $summary['k3_compliance'] }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="page-panel p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-extrabold text-slate-900">Daftar PA hari ini</h3>
                <span class="text-xs font-semibold text-slate-400">{{ $tasks->count() }} item</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($tasks as $task)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-600">{{ $task->pa_number }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $task->customer_name ?? '-' }}</p>
                            </div>
                            <x-badge-status :status="$task->current_status" />
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                            <span>{{ $task->region?->kabupaten_kota ?? '-' }}</span>
                            <span>{{ $task->evidences->count() }} bukti</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                        Belum ada PA yang ditugaskan hari ini.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
