@extends('layouts.admin')

@php
    $active = 'pa';
    $title = 'Riwayat Upload PA';
    $subtitle = 'Status pemrosesan dan laporan hasil import';
@endphp

@section('title', 'Riwayat Upload PA')

@section('content')
    <section class="page-panel p-5 sm:p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Riwayat Upload</h2>
                <p class="mt-1 text-sm text-slate-500">Lihat hasil pemrosesan setiap file dan unduh baris yang gagal.</p>
            </div>
            <a href="{{ route('admin.pa.upload') }}" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Upload data PA</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-3 font-semibold">Waktu</th>
                        <th class="px-3 py-3 font-semibold">Nama file</th>
                        <th class="px-3 py-3 font-semibold">Pengunggah</th>
                        <th class="px-3 py-3 text-right font-semibold">Baris</th>
                        <th class="px-3 py-3 text-right font-semibold">Berhasil</th>
                        <th class="px-3 py-3 text-right font-semibold">Gagal</th>
                        <th class="px-3 py-3 font-semibold">Status</th>
                        <th class="px-3 py-3 text-right font-semibold">Laporan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($uploadBatches as $batch)
                        @php
                            $statusLabel = [
                                'queued' => 'Dalam antrean',
                                'processing' => 'Diproses',
                                'completed' => 'Selesai',
                                'failed' => 'Gagal',
                            ][$batch->status] ?? $batch->status;
                            $statusClass = match ($batch->status) {
                                'completed' => 'bg-emerald-50 text-emerald-700',
                                'failed' => 'bg-rose-50 text-rose-700',
                                'processing' => 'bg-amber-50 text-amber-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $batch->uploaded_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="max-w-xs truncate px-3 py-3 font-medium text-slate-800" title="{{ $batch->file_name }}">{{ $batch->file_name }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $batch->uploader?->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-slate-600">{{ number_format($batch->total_rows) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-emerald-700">{{ number_format($batch->success_rows) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-rose-700">{{ number_format($batch->failed_rows) }}</td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="px-3 py-3 text-right">
                                @if ($batch->error_report_path)
                                    <a href="{{ route('admin.pa.upload-history.errors', $batch) }}" class="font-semibold text-sky-700 hover:text-sky-900">Unduh error</a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-10 text-center text-slate-500">Belum ada riwayat upload.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $uploadBatches->links() }}</div>
    </section>
@endsection