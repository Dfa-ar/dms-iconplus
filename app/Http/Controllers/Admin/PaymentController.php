<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaOrder;
use App\Models\PaymentBatch;
use App\Models\PaymentItem;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->dateFilters($request);
        $paOrders = PaOrder::with(['region', 'currentOfficer.user', 'bastDocument'])
            ->where(function ($query) {
                $query->whereNotNull('bast_document_id')
                    ->orWhereNotNull('payment_status');
            })
            ->latest('updated_at')
            ->get();
        $eligiblePaOrders = PaOrder::with(['region', 'bastDocument'])
            ->whereNotNull('bast_document_id')
            ->where('current_status', '!=', PaOrder::BLUEPRINT_STATUS_BAST_VOID)
            ->where(function ($query) {
                $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'PAID');
            })
            ->whereHas('bastDocument', fn ($query) => $query->where('status', 'FINAL'))
            ->orderBy('pa_number')
            ->get();

        $batches = PaymentBatch::with(['creator', 'items.paOrder.region'])
            ->withSum('items', 'amount')
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('payment_date', '<=', $date))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $summaryQuery = PaymentItem::query()
            ->whereHas('paymentBatch', function (Builder $query) use ($filters) {
                $query->when($filters['from_date'] ?? null, fn (Builder $batchQuery, $date) => $batchQuery->whereDate('payment_date', '>=', $date))
                    ->when($filters['to_date'] ?? null, fn (Builder $batchQuery, $date) => $batchQuery->whereDate('payment_date', '<=', $date));
            });
        $summary = (clone $summaryQuery)
            ->selectRaw('COUNT(*) as item_count, COALESCE(SUM(amount), 0) as total_amount')
            ->first();
        $methodTotals = (clone $summaryQuery)
            ->join('payment_batches', 'payment_batches.id', '=', 'payment_items.payment_batch_id')
            ->selectRaw('payment_batches.method, COUNT(*) as item_count, SUM(payment_items.amount) as total_amount')
            ->groupBy('payment_batches.method')
            ->orderBy('payment_batches.method')
            ->get();

        return view('admin.payments.index', compact(
            'paOrders',
            'eligiblePaOrders',
            'batches',
            'filters',
            'summary',
            'methodTotals'
        ));
    }

    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:BANK_TRANSFER,CASH,E_WALLET,OTHER'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'proof_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'pa_ids' => ['required', 'array', 'min:1', 'max:100'],
            'pa_ids.*' => ['required', 'integer', 'distinct', 'exists:pa_orders,id'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $amounts = $validated['amounts'];
        foreach ($validated['pa_ids'] as $paId) {
            if (! isset($amounts[$paId]) || ! is_numeric($amounts[$paId]) || (float) $amounts[$paId] <= 0) {
                throw ValidationException::withMessages([
                    "amounts.{$paId}" => 'Nominal pembayaran wajib diisi dan harus lebih dari nol untuk setiap PA terpilih.',
                ]);
            }
        }

        $proofPath = $request->file('proof_file')->store('payments/' . now()->format('Y'), 'local');

        try {
            $batch = DB::transaction(function () use ($request, $validated, $amounts, $proofPath) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, PaOrder> $paOrders */
                $paOrders = PaOrder::with('bastDocument')
                    ->whereIn('id', $validated['pa_ids'])
                    ->lockForUpdate()
                    ->get();

                $eligibleOrders = $paOrders->filter(fn (PaOrder $paOrder) =>
                    $paOrder->bastDocument?->status === 'FINAL'
                    && $paOrder->current_status !== PaOrder::BLUEPRINT_STATUS_BAST_VOID
                    && $paOrder->payment_status !== 'PAID'
                );

                if ($eligibleOrders->count() !== count($validated['pa_ids'])) {
                    throw ValidationException::withMessages([
                        'pa_ids' => 'Semua PA harus memiliki BAST final yang aktif dan belum lunas.',
                    ]);
                }

                $batch = PaymentBatch::create([
                    'payment_date' => $validated['payment_date'],
                    'method' => $validated['method'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'proof_path' => $proofPath,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $totalAmount = 0.0;

                foreach ($eligibleOrders as $paOrder) {
                    $amount = round((float) $amounts[$paOrder->id], 2);
                    $totalAmount += $amount;
                    $batch->items()->create([
                        'pa_id' => $paOrder->id,
                        'amount' => $amount,
                    ]);

                    $previousStatus = $paOrder->current_status;
                    $paOrder->update([
                        'payment_status' => 'PAID',
                        'current_status' => PaOrder::BLUEPRINT_STATUS_PAID,
                    ]);
                    $paOrder->statusLogs()->create([
                        'from_status' => $previousStatus,
                        'to_status' => PaOrder::BLUEPRINT_STATUS_PAID,
                        'changed_by' => $request->user()->id,
                        'note' => "Pembayaran batch #{$batch->id} tercatat sebesar Rp " . number_format($amount, 2, ',', '.') . '.',
                        'changed_at' => now(),
                    ]);
                }

                AuditLogger::log(
                    'payment_batch_created',
                    'PaymentBatch',
                    $batch->id,
                    sprintf(
                        'Batch pembayaran #%d untuk %d PA, total Rp %s, metode %s.',
                        $batch->id,
                        $eligibleOrders->count(),
                        number_format($totalAmount, 2, ',', '.'),
                        $batch->method
                    )
                );

                return $batch;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        return Redirect::route('admin.payments.index')->with('success', "Batch pembayaran #{$batch->id} berhasil dicatat.");
    }

    public function proof(PaymentBatch $paymentBatch)
    {
        abort_unless(Storage::disk('local')->exists($paymentBatch->proof_path), 404);

        return response()->file(Storage::disk('local')->path($paymentBatch->proof_path));
    }

    public function export(Request $request)
    {
        $filters = $this->dateFilters($request);
        $items = PaymentItem::with([
            'paymentBatch' => fn ($query) => $query->withSum('items', 'amount'),
            'paOrder.region',
        ])
            ->whereHas('paymentBatch', function (Builder $query) use ($filters) {
                $query->when($filters['from_date'] ?? null, fn (Builder $batchQuery, $date) => $batchQuery->whereDate('payment_date', '>=', $date))
                    ->when($filters['to_date'] ?? null, fn (Builder $batchQuery, $date) => $batchQuery->whereDate('payment_date', '<=', $date));
            })
            ->orderBy('id')
            ->lazy(500);

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Batch', 'Referensi', 'Metode', 'No PA', 'ID Pelanggan', 'Wilayah', 'Nominal PA', 'Total Batch', 'Catatan Batch']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->paymentBatch->payment_date?->format('Y-m-d'),
                    $item->payment_batch_id,
                    $item->paymentBatch->reference_no,
                    $item->paymentBatch->method,
                    $item->paOrder?->pa_number,
                    $item->paOrder?->customer_id,
                    $item->paOrder?->region?->kabupaten_kota,
                    number_format((float) $item->amount, 2, '.', ''),
                    number_format((float) $item->paymentBatch->items_sum_amount, 2, '.', ''),
                    $item->paymentBatch->notes,
                ]);
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, 'rekap-pembayaran-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function update(Request $request, PaOrder $paOrder)
    {
        if ($paOrder->current_status === PaOrder::BLUEPRINT_STATUS_BAST_VOID || $paOrder->bastDocument?->status === 'VOID') {
            return back()->withErrors(['payment_status' => 'Pembayaran tidak dapat diperbarui karena BAST sudah dibatalkan.']);
        }
        if ($paOrder->paymentItems()->exists()) {
            return back()->withErrors(['payment_status' => 'Status PA sudah memiliki transaksi pembayaran. Koreksi ledger harus melalui alur pembatalan pembayaran.']);
        }

        $validated = $request->validate([
            'payment_status' => 'required|in:PAID,REJECTED,PENDING',
            'payment_note' => 'nullable|string|max:1000',
        ]);

        if ($validated['payment_status'] === 'PAID') {
            return back()->withErrors(['payment_status' => 'Catat pembayaran melalui formulir batch agar nominal dan bukti tersimpan.']);
        }

        $previousStatus = $paOrder->current_status;
        $previousPaymentStatus = $paOrder->payment_status;
        $nextOperationalStatus = $validated['payment_status'] === 'PENDING' && $paOrder->bast_document_id
            ? PaOrder::BLUEPRINT_STATUS_BAST_ISSUED
            : $previousStatus;
        $note = trim((string) ($validated['payment_note'] ?? ''));

        DB::transaction(function () use ($request, $paOrder, $validated, $previousStatus, $previousPaymentStatus, $nextOperationalStatus, $note) {
            $paOrder->update([
                'payment_status' => $validated['payment_status'],
                'current_status' => $nextOperationalStatus,
                'notes' => $note !== '' ? trim(($paOrder->notes ?? '') . PHP_EOL . $note) : $paOrder->notes,
            ]);

            $paOrder->statusLogs()->create([
                'from_status' => $previousStatus,
                'to_status' => $nextOperationalStatus,
                'changed_by' => $request->user()->id,
                'note' => $note !== '' ? $note : 'Status pembayaran diperbarui.',
                'changed_at' => now(),
            ]);

            AuditLogger::log(
                'payment_status_updated',
                'PaOrder',
                $paOrder->id,
                "Status pembayaran PA {$paOrder->pa_number} berubah dari " . ($previousPaymentStatus ?? 'PENDING') . " menjadi {$validated['payment_status']}. " . $note
            );
        });

        return Redirect::route('admin.payments.index')->with('success', "Status pembayaran PA {$paOrder->pa_number} berhasil diperbarui.");
    }

    private function dateFilters(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
    }
}