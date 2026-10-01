@extends('layouts.admin')

@php
    $active = 'dashboard';
    $title = 'Kantor Perwakilan';
    $subtitle = 'Master entitas formal Kantor Perwakilan';
@endphp

@section('title', 'Kantor Perwakilan')

@section('content')
    <div class="space-y-5">
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="page-kicker">Master data</span>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Kantor Perwakilan</h2>
                <p class="mt-1 text-sm text-slate-500">Entitas formal untuk lokasi operasional, wilayah, dan penanggung jawab KP.</p>
            </div>
        </div>

        <div class="page-panel p-5">
            <form method="POST" action="{{ route('admin.kantor-perwakilan.store') }}" class="grid w-full gap-3 md:grid-cols-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Nama KP</label>
                    <input type="text" name="nama" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Kantor Perwakilan Tasikmalaya" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kode KP</label>
                    <input type="text" name="kode" maxlength="30" pattern="[A-Za-z0-9_-]+" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm uppercase text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="TSM" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">PIC</label>
                    <input type="text" name="pic_nama" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Yuniar">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Unit PIC</label>
                    <input type="text" name="pic_unit" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Inventory KP Tasikmalaya">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Alamat</label>
                    <input type="text" name="alamat" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Jl. ...">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Koordinat</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.0001" name="latitude" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Latitude">
                        <input type="number" step="0.0001" name="longitude" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Longitude">
                    </div>
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Tambah Kantor Perwakilan</button>
                </div>
            </form>
        </div>

        <div class="page-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">KP</th>
                            <th class="px-5 py-3 font-medium">PIC</th>
                            <th class="px-5 py-3 font-medium">Lokasi</th>
                            <th class="px-5 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($kantorPerwakilan as $kp)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('admin.kantor-perwakilan.update', $kp) }}" class="mb-2 flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="nama" value="{{ $kp->nama }}">
                                        <input type="text" name="kode" value="{{ $kp->kode }}" maxlength="30" pattern="[A-Za-z0-9_-]+" aria-label="Kode KP {{ $kp->nama }}" class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-xs uppercase" required>
                                        <button type="submit" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">Simpan kode</button>
                                    </form>
                                    <p class="font-semibold text-slate-800">{{ $kp->nama }}</p>
                                    <p class="text-xs text-slate-400">{{ $kp->alamat ?? '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <div>{{ $kp->pic_nama ?? '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ $kp->pic_unit ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-500">
                                    <div>{{ $kp->latitude ?? '-' }} / {{ $kp->longitude ?? '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ $kp->regions_count ?? 0 }} wilayah · {{ $kp->officers_count ?? 0 }} petugas</div>
                                </td>
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('admin.kantor-perwakilan.destroy', $kp) }}" onsubmit="return confirm('Hapus Kantor Perwakilan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada Kantor Perwakilan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($kantorPerwakilan->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $kantorPerwakilan->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
