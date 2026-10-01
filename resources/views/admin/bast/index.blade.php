@extends('layouts.admin')

@php
    $active = 'bast';
    $title = 'BAST';
    $subtitle = 'Generate dokumen serah terima untuk PA yang sudah lolos QC';
@endphp

@section('title', 'BAST')

@section('content')
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="page-kicker">BAST</span>
            <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Dokumen Serah Terima</h2>
            <p class="mt-1 text-sm text-slate-500">Generate dokumen serah terima untuk PA yang sudah lolos QC.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="page-panel p-5">
            <h2 class="text-lg font-bold text-slate-900">Dokumen BAST</h2>

            @if($documents->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Belum ada BAST yang dibuat.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($documents as $document)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-800">{{ $document->nomor_bast }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $document->kantorPerwakilan?->kode ?? 'KP lama' }} · {{ $document->kantorPerwakilan?->nama ?? $document->region?->kabupaten_kota ?? '-' }} · {{ $document->tanggal }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $document->status === 'VOID' ? 'bg-rose-100 text-rose-700 ring-rose-200' : 'bg-sky-100 text-sky-700 ring-sky-200' }}">{{ $document->status }}</span>
                            </div>
                            @if($document->status === 'VOID')
                                <p class="mt-2 whitespace-pre-wrap break-words text-xs text-rose-700">Alasan void: {{ $document->void_reason }}</p>
                            @endif
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm text-slate-600">
                                <span>Item: {{ $document->items_count + $document->archived_items_count }} PA</span>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.bast.preview', $document) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 font-medium text-violet-700 hover:bg-violet-100">Preview</a>
                                    <a href="{{ route('admin.bast.download', $document) }}" class="inline-flex items-center rounded-lg border border-sky-200 bg-white px-3 py-1.5 font-medium text-sky-700 hover:bg-sky-50">Download PDF</a>
                                    @if($document->status === 'FINAL')
                                        <details class="relative">
                                            <summary class="cursor-pointer rounded-lg border border-rose-200 bg-white px-3 py-1.5 font-medium text-rose-700 hover:bg-rose-50">Void</summary>
                                            <form method="POST" action="{{ route('admin.bast.void', $document) }}" class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                                                @csrf
                                                <label class="block text-xs font-semibold text-slate-700">Alasan pembatalan</label>
                                                <textarea name="void_reason" rows="3" required minlength="5" maxlength="1000" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Jelaskan alasan BAST dibatalkan"></textarea>
                                                <button type="submit" class="w-full rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-800">Konfirmasi void</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="page-panel p-5">
            <h2 class="text-lg font-bold text-slate-900">Buat BAST Baru</h2>
            <form method="GET" action="{{ route('admin.bast.index') }}" class="mt-4 flex items-end gap-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kantor Perwakilan</label>
                    <select name="kantor_perwakilan_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" required @disabled($kantorPerwakilan->isEmpty())>
                        <option value="">Pilih KP</option>
                        @foreach($kantorPerwakilan as $kp)
                            <option value="{{ $kp->id }}" @selected($selectedKantorPerwakilan?->id === $kp->id)>{{ $kp->kode ?: 'Kode belum diatur' }} · {{ $kp->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tampilkan PA</button>
            </form>

            @if($kantorPerwakilan->isEmpty())
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Master Kantor Perwakilan masih kosong.</p>
                    <p class="mt-1 text-xs">Tambahkan KP beserta kode resminya agar pilihan batch BAST tersedia.</p>
                    <a href="{{ route('admin.kantor-perwakilan.index') }}" class="mt-3 inline-flex rounded-lg bg-amber-900 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800">Buka master KP</a>
                </div>
            @endif

            @if($selectedKantorPerwakilan)
                <form method="POST" action="{{ route('admin.bast.generate') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="kantor_perwakilan_id" value="{{ $selectedKantorPerwakilan->id }}">
                    <p class="text-xs text-slate-500">Nomor BAST menggunakan kode {{ $selectedKantorPerwakilan->kode ?: 'KP belum dikonfigurasi' }}.</p>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">PA yang lolos QC</label>
                    <div class="space-y-2">
                        @forelse($paOrders as $paOrder)
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                                <input type="checkbox" name="pa_ids[]" value="{{ $paOrder->id }}" class="h-4 w-4 rounded border-slate-300 text-sky-600">
                                <span class="flex flex-col">
                                    <span class="font-semibold text-slate-800">{{ $paOrder->pa_number }}</span>
                                    <span class="text-xs text-slate-500">ID Pelanggan: {{ $paOrder->customer_id ?? '-' }} · Wilayah: {{ $paOrder->region?->kabupaten_kota ?? '-' }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada PA lolos QC yang tersedia untuk KP ini.</p>
                        @endforelse
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Pihak Yang Menyerahkan</label>
                        <input type="text" name="pihak_menyerahkan" value="PT Icon Plus" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" placeholder="Nama penyedia / perusahaan">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Pihak Yang Menerima</label>
                        <input type="text" name="pihak_menerima" value="Pelanggan" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" placeholder="Nama penerima / pelanggan">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Jabatan Penyerah</label>
                        <input type="text" name="jabatan_menyerahkan" value="Manager Operasional" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Jabatan Penerima</label>
                        <input type="text" name="jabatan_menerima" value="PIC Pelanggan" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Lokasi Serah Terima</label>
                        <input type="text" name="lokasi" value="{{ $selectedKantorPerwakilan->nama }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" placeholder="Nama lokasi / wilayah">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
                    <textarea name="notes" rows="3" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" placeholder="Opsional"></textarea>
                </div>

                <button type="submit" class="w-full rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700">Generate BAST</button>
            </form>
            @else
                <p class="mt-4 text-sm text-slate-500">Pilih KP untuk melihat PA yang tersedia dalam batch tersebut.</p>
            @endif
        </section>
    </div>
@endsection
