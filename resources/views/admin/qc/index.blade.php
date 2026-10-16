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

    <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">QC massal</div>
                <div class="flex flex-wrap gap-2 text-xs text-slate-700">
                    @foreach(App\Models\PaOrder::blueprintQcCategories() as $key => $category)
                        <span class="rounded-full border border-sky-200 bg-white px-2.5 py-1 font-medium">{{ $key }}. {{ $category['label'] }}</span>
                    @endforeach
                </div>
            </div>
            <label class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700">
                <input type="checkbox" data-select-all-qc form="bulk-qc-form" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Pilih semua
            </label>
        </div>

        <form id="bulk-qc-form" method="POST" action="{{ route('admin.qc.bulk-review') }}" class="space-y-4 rounded-2xl border border-sky-100 bg-white p-4">
            @csrf
            <div class="grid gap-3 lg:grid-cols-[1.4fr_1fr]">
                <div class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left sm:grid-cols-2">
                    @foreach(App\Models\PaOrder::blueprintQcChecklist() as $key => $label)
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" name="qc_checks[{{ $key }}]" value="1" form="bulk-qc-form" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" checked>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <textarea name="qc_note" rows="3" form="bulk-qc-form" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-400 focus:outline-none" placeholder="Catatan QC umum untuk PA terpilih..."></textarea>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <select name="reason_id" form="bulk-qc-form" class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700">
                            <option value="">-- Pilih alasan --</option>
                            @foreach($reasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" form="bulk-qc-form" name="decision" value="approve" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">Approve terpilih</button>
                        <button type="submit" form="bulk-qc-form" name="decision" value="reject" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Reject terpilih</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="page-panel overflow-x-auto">
        <div class="min-w-[1200px]">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium"><input type="checkbox" data-select-row-qc form="bulk-qc-form" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" aria-label="Pilih semua PA"></th>
                        <th class="px-5 py-3 font-medium">No PA</th>
                        <th class="px-5 py-3 font-medium">ID Pelanggan</th>
                        <th class="px-5 py-3 font-medium">Bukti Foto</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Petugas</th>
                        <th class="px-5 py-3 font-medium">Status QC</th>
                        <th class="px-5 py-3 font-medium">Catatan</th>
                        <th class="w-[350px] max-w-[350px] px-5 py-3 text-left font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($paOrders as $paOrder)
                        <tr class="hover:bg-slate-50/70 align-top">
                            <td class="px-5 py-4">
                                <input type="checkbox" name="pa_ids[]" value="{{ $paOrder->id }}" form="bulk-qc-form" class="qc-row-checkbox h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" aria-label="Pilih PA {{ $paOrder->pa_number }}">
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $paOrder->pa_number }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $paOrder->customer_id ?? '-' }}</td>
                            <td class="px-5 py-4">
                                @if($paOrder->evidences->isNotEmpty())
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($paOrder->evidences->take(4) as $evidence)
                                            <a href="{{ route('evidence.show', $evidence) }}" target="_blank" class="inline-block overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm hover:border-sky-300">
                                                <img src="{{ route('evidence.show', $evidence) }}" alt="Bukti {{ $evidence->type }}" class="h-16 w-16 object-cover">
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
                            <td class="px-5 py-4 text-slate-600">{{ $paOrder->qc_note ?? '-' }}</td>
                            <td class="w-[350px] max-w-[350px] px-5 py-4 align-top">
                                <form method="POST" action="{{ route('admin.qc.review', $paOrder) }}" class="flex w-full max-w-[320px] flex-col gap-3">
                                    @csrf
                                    <div class="grid w-full gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left sm:grid-cols-2">
                                        @foreach(App\Models\PaOrder::blueprintQcChecklist() as $key => $label)
                                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                                <input type="checkbox" name="qc_checks[{{ $key }}]" value="1" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" checked>
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                                        <select name="reason_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700 sm:max-w-[180px]">
                                            <option value="">-- Pilih alasan --</option>
                                            @foreach($reasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="flex gap-2">
                                            <button type="submit" name="decision" value="approve" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                            <button type="submit" name="decision" value="reject" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500">Reject</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400">Belum ada PA yang masuk daftar QC.</td>
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
        document.addEventListener('change', function (event) {
            const target = event.target;
            if (! target) {
                return;
            }

            if (target.matches('[data-select-all-qc]')) {
                document.querySelectorAll('.qc-row-checkbox').forEach((checkbox) => {
                    checkbox.checked = target.checked;
                });
                document.querySelectorAll('[data-select-row-qc]').forEach((headerCheckbox) => {
                    headerCheckbox.checked = target.checked;
                });
                return;
            }

            if (target.matches('[data-select-row-qc]')) {
                document.querySelectorAll('.qc-row-checkbox').forEach((checkbox) => {
                    checkbox.checked = target.checked;
                });
                document.querySelectorAll('[data-select-all-qc]').forEach((allCheckbox) => {
                    allCheckbox.checked = target.checked;
                });
            }
        });
    </script>
@endsection
