@extends('layouts.admin')

@php
    $active = 'qc';
    $title = 'QC PA';
    $subtitle = 'Review hasil kerja petugas sebelum dinyatakan siap BAST';
@endphp

@section('title', 'QC PA')

@section('content')
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="page-kicker">Quality Control</span>
            <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">QC PA</h2>
            <p class="mt-1 text-sm text-slate-500">Review hasil kerja petugas sebelum dinyatakan siap BAST.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.qc.index') }}" class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_auto_auto]">
        <select name="region_id" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
            <option value="">Semua wilayah</option>
            @foreach($regions as $region)
                <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->kabupaten_kota }}</option>
            @endforeach
        </select>

        <select name="per_page" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-400 focus:bg-white">
            @foreach([10, 15, 25, 50] as $value)
                <option value="{{ $value }}" @selected((string) request('per_page', 15) === (string) $value)>{{ $value }} / halaman</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Terapkan</button>
    </form>

    @if($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form id="bulk-qc-form" method="POST" action="{{ route('admin.qc.bulk-review') }}" class="mb-4 space-y-4 rounded-xl border border-slate-200 bg-white p-4">
        @csrf
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900">QC massal</h3>
                <p class="mt-1 text-xs text-slate-500">Maksimal 50 PA. Checklist dan keputusan diterapkan pada semua PA terpilih.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <select id="bulk-qc-decision" name="decision" required class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <option value="">Keputusan</option>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                </select>
                <select id="bulk-qc-reason" name="reason_id" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <option value="">Alasan penolakan</option>
                    @foreach($reasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Proses QC terpilih</button>
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach(App\Models\PaOrder::blueprintQcCategories() as $category => $details)
                <fieldset class="rounded-lg border border-slate-200 p-3">
                    <legend class="px-1 text-xs font-bold text-slate-700">{{ $category }}. {{ $details['label'] }}</legend>
                    <div class="space-y-2">
                        @foreach($details['checks'] as $key => $label)
                            <label class="flex items-start gap-2 text-xs text-slate-700">
                                <input type="hidden" name="qc_checks[{{ $key }}]" value="0" form="bulk-qc-form">
                                <input type="checkbox" name="qc_checks[{{ $key }}]" value="1" form="bulk-qc-form" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
    </form>

    <div class="page-panel overflow-hidden">
        <div class="overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Tabel QC, geser secara horizontal untuk melihat kolom lainnya">
            <table class="min-w-[1500px] w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-3 py-3 font-medium"><span class="sr-only">Pilih PA untuk QC massal</span></th>
                        <th class="px-5 py-3 font-medium">No PA</th>
                        <th class="px-5 py-3 font-medium">ID Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Bukti Foto</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Petugas</th>
                        <th class="px-5 py-3 font-medium">Status QC</th>
                        <th class="w-72 min-w-72 px-5 py-3 font-medium">Catatan</th>
                        <th class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($paOrders as $paOrder)
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-3 py-4"><input type="checkbox" name="pa_ids[]" value="{{ $paOrder->id }}" form="bulk-qc-form" aria-label="Pilih {{ $paOrder->pa_number }} untuk QC massal" class="h-4 w-4 rounded border-slate-300 text-sky-600"></td>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $paOrder->pa_number }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $paOrder->customer_id ?? '-' }}</td>
                            <td class="px-5 py-4">
                                @if($paOrder->evidences->isNotEmpty())
                                    <div class="flex max-w-md flex-wrap gap-2">
                                        @foreach($paOrder->evidences as $evidence)
                                            <a href="{{ route('evidence.show', $evidence) }}" target="_blank" class="flex w-24 flex-col gap-1 overflow-hidden rounded-lg border border-slate-200 bg-white p-1 text-left hover:border-sky-300">
                                                @if($evidence->type === 'ba_pengambilan_perangkat' && strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION)) === 'pdf')
                                                    <span class="flex h-16 items-center justify-center bg-slate-100 text-xs font-bold text-slate-600">PDF</span>
                                                @else
                                                    <img src="{{ route('evidence.show', $evidence) }}" alt="Bukti {{ str_replace('_', ' ', $evidence->type) }}" class="h-16 w-full object-cover">
                                                @endif
                                                <span class="truncate text-[10px] font-medium text-slate-600">{{ str_replace('_', ' ', $evidence->type) }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Belum ada foto</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $paOrder->region?->kabupaten_kota ?? '-' }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $paOrder->currentOfficer?->user?->name ?? '-' }}</td>
                            <td class="px-5 py-4">
                                @if($paOrder->qc_status === 'PASSED')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">PASSED</span>
                                @elseif($paOrder->qc_status === 'REJECTED')
                                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-red-200">REJECTED</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200">PENDING</span>
                                @endif
                            </td>
                            <td class="w-72 min-w-72 px-5 py-4 text-slate-600"><div class="max-h-32 overflow-auto whitespace-pre-wrap break-words">{{ $paOrder->qc_note ?? '-' }}</div></td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('admin.qc.review', $paOrder) }}" class="flex flex-col items-end gap-3">
                                    @csrf
                                    <div class="grid w-full gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left sm:grid-cols-2">
                                        @foreach(App\Models\PaOrder::blueprintQcCategories() as $category => $details)
                                            <fieldset class="space-y-2 rounded-lg border border-slate-200 bg-white p-2">
                                                <legend class="px-1 text-[11px] font-bold text-slate-700">{{ $category }}. {{ $details['label'] }}</legend>
                                                @foreach($details['checks'] as $key => $label)
                                                    <label class="flex items-center gap-2 text-xs text-slate-700">
                                                        <input type="hidden" name="qc_checks[{{ $key }}]" value="0">
                                                        <input type="checkbox" name="qc_checks[{{ $key }}]" value="1" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" @checked(old("qc_checks.{$key}") === '1')>
                                                        <span>{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </fieldset>
                                        @endforeach
                                    </div>
                                    <div class="flex w-full flex-col items-end gap-2 sm:flex-row sm:items-center">
                                        <select name="reason_id" class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                            <option value="">-- Pilih alasan --</option>
                                            @foreach($reasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" name="decision" value="approve" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada PA yang masuk daftar QC.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($paOrders->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $paOrders->links() }}
            </div>
        @endif
    </div>
    <script>
        const bulkDecision = document.getElementById('bulk-qc-decision');
        const bulkReason = document.getElementById('bulk-qc-reason');
        const updateBulkReason = () => {
            bulkReason.required = bulkDecision.value === 'reject';
        };
        bulkDecision.addEventListener('change', updateBulkReason);
        updateBulkReason();
    </script>
@endsection
