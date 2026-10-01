@extends('layouts.petugas')

@php
    $active = 'dashboard';
    $title = 'Tugas Saya';
    $subtitle = 'Eksekusi deaktivasi hari ini';
    $completion = $target > 0 ? round(($progress / $target) * 100) : 0;
@endphp

@section('title', 'Tugas Saya')

@section('content')
    <div class="mx-auto w-full max-w-[440px] lg:mx-0 lg:max-w-none" data-reveal>
        <section class="relative overflow-hidden rounded-[24px] bg-[#0D5A71] p-5 text-white shadow-xl shadow-cyan-950/10 sm:p-6">
            <div class="pointer-events-none absolute -right-12 -top-16 h-48 w-48 rounded-full border-[24px] border-white/5"></div>
            <div class="pointer-events-none absolute bottom-0 left-0 h-32 w-32 rounded-full bg-white/5 blur-2xl"></div>

            <div class="relative z-10 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/70">Operasional hari ini</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Tugas Saya</h2>
                    <p class="mt-1 text-sm text-cyan-50/70">{{ now()->translatedFormat('l, d F Y') }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-3 py-2 text-right ring-1 ring-white/10 backdrop-blur">
                    <p class="text-2xl font-extrabold">{{ $progress }}<span class="text-base font-medium text-cyan-100/60">/{{ $target }}</span></p>
                    <p class="text-[10px] uppercase tracking-wide text-cyan-100/70">Selesai</p>
                </div>
            </div>

            <div class="relative z-10 mt-7">
                <div class="mb-2 flex justify-between text-xs font-semibold text-cyan-50/80">
                    <span>Progress pekerjaan</span>
                    <span>{{ $completion }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full bg-[#FFF200] transition-all duration-700" style="width: {{ $completion }}%"></div>
                </div>
            </div>
        </section>

        <div class="mt-5 grid grid-cols-3 gap-3">
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Total</p>
                <p class="mt-2 text-xl font-extrabold text-slate-900">{{ $target }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Selesai</p>
                <p class="mt-2 text-xl font-extrabold text-emerald-600">{{ $progress }}</p>
            </div>
            <div class="page-panel p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Kendala</p>
                <p class="mt-2 text-xl font-extrabold text-amber-600">{{ $tasks->where('current_status', 'KENDALA')->count() }}</p>
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900">Daftar PA</h2>
                <p class="text-sm text-slate-500">{{ $tasks->count() }} tugas ditugaskan hari ini</p>
            </div>
            <a href="{{ route('petugas.tasks.history') }}" class="flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 shadow-sm">Riwayat</a>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($tasks as $task)
                <a href="{{ route('petugas.tasks.show', $task) }}" class="field-card app-card block p-4" data-reveal>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold tracking-[0.14em] text-sky-600">{{ $task->pa_number }}</p>
                            <div class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                                <span class="font-semibold uppercase tracking-[0.1em] text-slate-400">ID Pelanggan</span>
                                <span class="text-slate-600">{{ $task->customer_id ?? '-' }}</span>
                            </div>
                        </div>
                        <x-badge-status :status="$task->current_status" />
                    </div>

                    <div class="mt-4 flex items-start gap-2 text-sm text-slate-500">
                        <x-dashboard-icon name="location" class="mt-0.5 h-4 w-4 flex-none text-slate-400" />
                        <span class="line-clamp-2">{{ $task->address ?: 'Alamat belum tersedia' }}</span>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs font-semibold text-slate-400">
                        <span>{{ $task->region?->kabupaten_kota ?? '-' }}</span>
                        <span class="text-sky-600">Lihat detail &rarr;</span>
                    </div>
                </a>
            @empty
                <div class="app-card px-5 py-12 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-50 text-sky-500"><x-dashboard-icon name="check" class="h-7 w-7" /></div>
                    <h3 class="mt-4 font-bold text-slate-800">Belum ada tugas hari ini</h3>
                    <p class="mt-1 text-sm text-slate-500">Tugas baru akan muncul setelah Admin melakukan assignment.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
