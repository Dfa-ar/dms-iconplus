@extends('layouts.admin')

@php
    $active = 'dashboard';
    $title = 'Master Splitter';
    $subtitle = 'Kelola splitter dan port allocation per FAT';
@endphp

@section('title', 'Master Splitter')

@section('content')
    <div class="space-y-5">
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="page-kicker">Master data</span>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Daftar Splitter</h2>
                <p class="mt-1 text-sm text-slate-500">Kelola port lock dan kapasitas splitter berdasarkan FAT.</p>
            </div>
        </div>

        <div class="page-panel p-5">
            <form method="POST" action="{{ route('admin.splitters.store') }}" class="grid w-full gap-3 md:grid-cols-5">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Kode Splitter</label>
                    <input type="text" name="kode_splitter" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="SP-001" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Nama Splitter</label>
                    <input type="text" name="nama_splitter" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none" placeholder="Splitter Soreang" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">FAT</label>
                    <select name="fat_point_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
                        <option value="">Pilih FAT</option>
                        @foreach ($fatPoints as $fatPoint)
                            <option value="{{ $fatPoint->id }}">{{ $fatPoint->kode_fat }} - {{ $fatPoint->nama_fat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Capacity Port</label>
                    <input type="number" name="capacity_port" min="1" max="512" value="24" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white focus:outline-none">
                </div>
                <div class="flex items-end">
                    <label class="flex h-[42px] items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        <span>Aktif</span>
                    </label>
                    <button type="submit" class="ml-3 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Tambah</button>
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
                            <th class="px-5 py-3 font-medium">FAT</th>
                            <th class="px-5 py-3 font-medium">Port</th>
                            <th class="px-5 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($splitters as $splitter)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-4 font-semibold text-slate-800">{{ $splitter->kode_splitter }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $splitter->nama_splitter }}</td>
                                <td class="px-5 py-4 text-slate-500">{{ $splitter->fatPoint?->kode_fat ?? '-' }}</td>
                                <td class="px-5 py-4 text-slate-500">{{ $splitter->total_port }}</td>
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('admin.splitters.destroy', $splitter) }}" onsubmit="return confirm('Hapus splitter ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada data splitter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($splitters->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $splitters->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
