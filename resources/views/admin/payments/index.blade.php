@extends('layouts.admin')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="page-kicker">Pembayaran</span>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900">Pembayaran PA</h1>
            <p class="mt-1 text-sm text-slate-500">Catat transaksi per batch, nominal per PA, metode, dan bukti pembayaran.</p>
        </div>
        <a href="{{ route('admin.payments.export', request()->query()) }}" class="inline-flex items-center gap-2 self-start rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 lg:self-auto">Ekspor CSV</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap items-end gap-3 border-b border-slate-200 pb-4">
        <label class="block text-xs font-semibold text-slate-600">Dari tanggal<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></label>
        <label class="block text-xs font-semibold text-slate-600">Sampai tanggal<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></label>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Terapkan filter</button>
        <a href="{{ route('admin.payments.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Reset</a>
    </form>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="page-panel p-4"><div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total transaksi</div><div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) ($summary?->item_count ?? 0)) }}</div></div>
        <div class="page-panel p-4"><div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Total nominal</div><div class="mt-2 text-2xl font-extrabold text-emerald-700">Rp {{ number_format((float) ($summary?->total_amount ?? 0), 2, ',', '.') }}</div></div>
        <div class="page-panel p-4"><div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Batch tercatat</div><div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $batches->total() }}</div></div>
    </div>
    <div class="flex flex-wrap gap-x-6 gap-y-2 border-b border-slate-200 pb-4 text-xs text-slate-600">
        <span class="font-semibold text-slate-800">Rekap metode:</span>
        @forelse($methodTotals as $methodTotal)
            <span>{{ str_replace('_', ' ', $methodTotal->method) }} · {{ $methodTotal->item_count }} PA · Rp {{ number_format((float) $methodTotal->total_amount, 2, ',', '.') }}</span>
        @empty
            <span>Belum ada transaksi pada rentang ini.</span>
        @endforelse
    </div>

    <section class="page-panel p-5">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">Catat pembayaran batch</h2>
            <p class="mt-1 text-xs text-slate-500">Satu batch memakai tanggal, metode, dan bukti yang sama. Nominal dicatat terpisah untuk setiap PA.</p>
        </div>
        <form method="POST" action="{{ route('admin.payments.batches.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block text-xs font-semibold text-slate-600">Tanggal pembayaran<input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-xs font-semibold text-slate-600">Metode<select name="method" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">Pilih metode</option><option value="BANK_TRANSFER" @selected(old('method') === 'BANK_TRANSFER')>Transfer bank</option><option value="CASH" @selected(old('method') === 'CASH')>Tunai</option><option value="E_WALLET" @selected(old('method') === 'E_WALLET')>E-wallet</option><option value="OTHER" @selected(old('method') === 'OTHER')>Lainnya</option></select></label>
                <label class="block text-xs font-semibold text-slate-600">Referensi transaksi<input type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="120" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nomor transfer / referensi"></label>
                <label class="block text-xs font-semibold text-slate-600">Bukti transfer / pembayaran<input type="file" name="proof_file" accept=".pdf,image/jpeg,image/png" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm"></label>
            </div>
            <div>
                <div class="mb-2 text-sm font-semibold text-slate-800">Pilih PA dan isi nominal diterima</div>
                <div class="max-h-72 overflow-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs text-slate-500"><tr><th class="px-3 py-2">PA</th><th class="px-3 py-2">Pelanggan / BAST</th><th class="px-3 py-2">Nominal (Rp)</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($eligiblePaOrders as $paOrder)
                                <tr>
                                    <td class="px-3 py-2"><label class="flex items-center gap-2"><input type="checkbox" name="pa_ids[]" value="{{ $paOrder->id }}" @checked(in_array((string) $paOrder->id, old('pa_ids', []), true)) class="h-4 w-4 rounded border-slate-300 text-sky-600"><span class="font-semibold">{{ $paOrder->pa_number }}</span></label></td>
                                    <td class="px-3 py-2 text-slate-600">{{ $paOrder->customer_name }}<span class="block text-xs text-slate-400">{{ $paOrder->bastDocument?->nomor_bast }}</span></td>
                                    <td class="px-3 py-2"><input type="number" name="amounts[{{ $paOrder->id }}]" value="{{ old("amounts.{$paOrder->id}") }}" min="0.01" max="9999999999999.99" step="0.01" inputmode="decimal" class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-label="Nominal pembayaran {{ $paOrder->pa_number }}"></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-sm text-slate-500">Belum ada PA dengan BAST final yang masih menunggu pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <label class="block text-xs font-semibold text-slate-600">Catatan batch<textarea name="notes" rows="2" maxlength="1000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Catatan opsional">{{ old('notes') }}</textarea></label>
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Simpan batch pembayaran</button></div>
        </form>
    </section>

    <section class="page-panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold text-slate-900">Rekap batch</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Metode / Referensi</th><th class="px-4 py-3">PA</th><th class="px-4 py-3">Nominal</th><th class="px-4 py-3">Bukti</th><th class="px-4 py-3">Pencatat</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($batches as $batch)
                        <tr class="align-top">
                            <td class="px-4 py-3">{{ $batch->payment_date->format('d/m/Y') }}<span class="block text-xs text-slate-400">Batch #{{ $batch->id }}</span></td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $batch->method) }}<span class="block text-xs text-slate-500">{{ $batch->reference_no ?? '-' }}</span></td>
                            <td class="px-4 py-3"><ul class="space-y-1">@foreach($batch->items as $item)<li>{{ $item->paOrder?->pa_number ?? '-' }} <span class="text-slate-500">Rp {{ number_format((float) $item->amount, 2, ',', '.') }}</span></li>@endforeach</ul></td>
                            <td class="px-4 py-3 font-semibold">Rp {{ number_format((float) $batch->items_sum_amount, 2, ',', '.') }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.payments.batches.proof', $batch) }}" target="_blank" rel="noopener" class="font-semibold text-sky-700 underline">Lihat bukti</a></td>
                            <td class="px-4 py-3">{{ $batch->creator?->name ?? '-' }}<span class="block max-w-52 whitespace-pre-wrap break-words text-xs text-slate-500">{{ $batch->notes }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada transaksi pembayaran pada rentang tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $batches->links() }}</div>@endif
    </section>

    <section class="page-panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold text-slate-900">Koreksi status pembayaran legacy</h2><p class="mt-1 text-xs text-slate-500">Status PAID hanya dapat dicatat melalui batch agar nominal dan bukti wajib tersimpan.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">PA / pelanggan</th><th class="px-4 py-3">BAST</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Koreksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($paOrders as $paOrder)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $paOrder->pa_number }}<span class="block text-xs font-normal text-slate-500">{{ $paOrder->customer_name }} · {{ $paOrder->region?->kabupaten_kota ?? '-' }}</span></td>
                            <td class="px-4 py-3">{{ $paOrder->bastDocument?->nomor_bast ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $paOrder->payment_status ?? 'PENDING' }}</td>
                            <td class="px-4 py-3"><form method="POST" action="{{ route('admin.payments.update', $paOrder) }}" class="flex flex-wrap items-center gap-2">@csrf<select name="payment_status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs"><option value="PENDING">PENDING</option><option value="REJECTED">REJECTED</option></select><input type="text" name="payment_note" maxlength="1000" placeholder="Alasan koreksi" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"><button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Simpan</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada PA yang memiliki BAST atau status pembayaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection