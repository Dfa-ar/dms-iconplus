@extends('layouts.admin')

@php
    $active = 'audit';
    $title = 'Audit Log';
    $subtitle = 'Riwayat perubahan dan aksi sensitif';
@endphp

@section('title', 'Audit Log')

@section('content')
<div class="space-y-5">
    <div><span class="page-kicker">Governance</span><h1 class="mt-3 text-2xl font-extrabold text-slate-900">Audit Log</h1></div>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="flex flex-wrap items-end gap-3 border-b border-slate-200 pb-4">
        <label class="text-xs font-semibold text-slate-600">Dari<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Sampai<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Pengguna<select name="user_id" class="mt-1 block min-w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">Semua pengguna</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Aksi<input name="action" value="{{ $filters['action'] ?? '' }}" maxlength="120" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: bast_voided"></label>
        <label class="text-xs font-semibold text-slate-600">Entitas<input name="entity" value="{{ $filters['entity'] ?? '' }}" maxlength="120" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: PaOrder"></label>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
        <a href="{{ route('admin.audit.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Reset</a>
    </form>

    <section class="page-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3">Aksi</th><th class="px-4 py-3">Entitas</th><th class="px-4 py-3">Detail</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="align-top"><td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td class="px-4 py-3">{{ $log->user?->name ?? 'Pengguna dihapus' }}</td><td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td><td class="px-4 py-3">{{ $log->entity }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td><td class="max-w-xl whitespace-pre-wrap break-words px-4 py-3 text-slate-600">{{ $log->detail }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada log untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>@endif
    </section>
</div>
@endsection