@extends('layouts.petugas')

@php
    $active = 'profile';
    $title = 'Profil Petugas';
    $subtitle = 'Info akun dan data operasional';
@endphp

@section('title', 'Profil Petugas')

@section('content')
    <div class="space-y-4 p-4" data-reveal>
        <section class="overflow-hidden rounded-[24px] bg-[#0D5A71] p-5 text-white shadow-xl shadow-cyan-950/10">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-2xl font-extrabold ring-1 ring-white/10">
                    {{ strtoupper(substr(($officer?->name ?? $user->name ?? 'P'), 0, 1)) }}
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/70">Petugas</p>
                    <h2 class="mt-1 text-xl font-extrabold">{{ $officer?->name ?? $user->name }}</h2>
                    <p class="text-sm text-cyan-50/70">{{ $officer?->employee_code ?? 'Belum ada NIP' }}</p>
                </div>
            </div>
        </section>

        <section class="app-card p-4">
            <h3 class="text-base font-bold text-slate-900">Data Akun</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-slate-500">Nama</span>
                    <span class="font-semibold text-slate-800">{{ $officer?->name ?? $user->name }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-slate-500">Email</span>
                    <span class="font-semibold text-slate-800">{{ $user->email }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-slate-500">Nomor HP</span>
                    <span class="font-semibold text-slate-800">{{ $officer?->phone ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-slate-500">NIP / Kode Petugas</span>
                    <span class="font-semibold text-slate-800">{{ $officer?->employee_code ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Wilayah</span>
                    <span class="font-semibold text-slate-800">{{ $officer?->region?->kabupaten_kota ?? 'Belum ditentukan' }}</span>
                </div>
            </div>
        </section>

        <section class="app-card p-4">
            <h3 class="text-base font-bold text-slate-900">Target Harian</h3>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Target</p>
                    <p class="mt-2 text-xl font-extrabold text-slate-900">{{ $officer?->daily_target ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Status</p>
                    <p class="mt-2 text-sm font-bold {{ $officer?->is_active ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $officer?->is_active ? 'Aktif' : 'Nonaktif' }}
                    </p>
                </div>
            </div>
        </section>
    </div>
@endsection
