@extends('layouts.admin')

@php
    $active = 'dashboard';
    $title = 'Master FAT';
    $subtitle = 'Kelola titik FAT dan keterkaitannya dengan wilayah';
@endphp

@section('title', 'Master FAT')

@section('content')
    <div class="space-y-5">
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="page-kicker">Master data</span>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Daftar FAT</h2>
                <p class="mt-1 text-sm text-slate-500">Kelola titik FAT untuk pengecekan port dan konektivitas.</p>
            </div>
        </div>

        <div class="page-panel p-5">
            <form method="POST" action="{{ route('admin.fat-points.store') }}" class="grid w-full gap-3 md:grid-cols-4">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kode FAT</label>
                    <input type="text" name="kode_fat" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="FAT-001" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Nama FAT</label>
                    <input type="text" name="nama_fat" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="FAT Soreang" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Wilayah</label>
                    <select name="region_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
                        <option value="">Pilih wilayah</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->kabupaten_kota }} {{ $region->kecamatan ? ' / ' . $region->kecamatan : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kantor Perwakilan</label>
                    <select name="kantor_perwakilan_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
                        <option value="">Pilih KP</option>
                        @foreach (App\Models\KantorPerwakilan::orderBy('nama')->get() as $kp)
                            <option value="{{ $kp->id }}">{{ $kp->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Koordinat</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.0001" name="latitude" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Lat">
                        <input type="number" step="0.0001" name="longitude" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Lng">
                    </div>
                </div>
                <div class="flex items-end">
                    <label class="flex h-[42px] items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span>Aktif</span>
                    </label>
                    <button type="submit" class="ml-3 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Tambah FAT</button>
                </div>
            </form>
        </div>

        <div class="page-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Kode</th>
                            <th class="px-5 py-3 font-medium">Nama</th>
                            <th class="px-5 py-3 font-medium">Wilayah</th>
                            <th class="px-5 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($fatPoints as $fatPoint)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $fatPoint->kode_fat }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $fatPoint->nama_fat }}</td>
                                <td class="px-5 py-4 text-slate-500">{{ $fatPoint->region?->kabupaten_kota ?? '-' }}</td>
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('admin.fat-points.destroy', $fatPoint) }}" onsubmit="return confirm('Hapus FAT ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data FAT.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($fatPoints->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $fatPoints->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
