@extends('layouts.admin')

@php
    $active = 'qc-reasons';
    $title = 'Alasan Tolak QC';
    $subtitle = 'Kelola alasan QC tanpa menghapus referensi riwayat PA';
@endphp

@section('title', 'Alasan Tolak QC')

@section('content')
<div class="space-y-5">
    <div>
        <span class="page-kicker">Master QC</span>
        <h1 class="mt-3 text-2xl font-extrabold text-slate-900">Alasan Penolakan QC</h1>
    </div>

    <section class="page-panel p-5">
        <h2 class="mb-4 text-sm font-bold text-slate-900">Tambah alasan</h2>
        <form method="POST" action="{{ route('admin.qc-reject-reasons.store') }}" class="grid gap-3 md:grid-cols-[1fr_1fr_2fr_auto]">
            @csrf
            <label class="text-xs font-semibold text-slate-600">Kode<input name="code" value="{{ old('code') }}" required maxlength="80" pattern="[A-Za-z0-9_-]+" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="foto_tidak_jelas"></label>
            <label class="text-xs font-semibold text-slate-600">Label<input name="label" value="{{ old('label') }}" required maxlength="150" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Bukti foto tidak jelas"></label>
            <label class="text-xs font-semibold text-slate-600">Deskripsi<textarea name="description" rows="1" maxlength="1000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Keterangan alasan"></textarea></label>
            <button type="submit" class="self-end rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Tambah</button>
        </form>
    </section>

    <section class="page-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Label</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Perubahan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reasons as $reason)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $reason->code }}</td>
                            <td class="px-4 py-3">
                                <form id="qc-reason-{{ $reason->id }}" method="POST" action="{{ route('admin.qc-reject-reasons.update', $reason) }}" class="grid gap-2 sm:grid-cols-3">
                                    @csrf
                                    @method('PATCH')
                                    <input name="code" value="{{ $reason->code }}" required maxlength="80" pattern="[A-Za-z0-9_-]+" aria-label="Kode {{ $reason->label }}" class="rounded-lg border border-slate-300 px-2 py-1.5 font-mono text-xs">
                                    <input name="label" value="{{ $reason->label }}" required maxlength="150" aria-label="Label {{ $reason->label }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                    <input name="description" value="{{ $reason->description }}" maxlength="1000" aria-label="Deskripsi {{ $reason->label }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs" placeholder="Deskripsi">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_active" value="1" @checked($reason->is_active) class="rounded border-slate-300 text-sky-600"> Aktif di QC</label>
                                </form>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $reason->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $reason->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="px-4 py-3"><div class="flex gap-2"><button type="submit" form="qc-reason-{{ $reason->id }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Simpan</button>@if($reason->is_active)<form method="POST" action="{{ route('admin.qc-reject-reasons.destroy', $reason) }}">@csrf @method('DELETE')<button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Nonaktifkan</button></form>@endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada alasan QC.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reasons->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $reasons->links() }}</div>@endif
    </section>
</div>
@endsection