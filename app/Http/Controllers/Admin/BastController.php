<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BastDocument;
use App\Models\BastItem;
use App\Models\KantorPerwakilan;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\SlaSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

class BastController extends Controller
{
    public function index(Request $request)
    {
        $documents = BastDocument::with(['region', 'creator', 'kantorPerwakilan'])
            ->withCount(['items', 'archivedItems'])
            ->latest()
            ->get();
        $kantorPerwakilan = KantorPerwakilan::orderBy('kode')->orderBy('nama')->get();
        $selectedKantorPerwakilan = $kantorPerwakilan->firstWhere('id', $request->integer('kantor_perwakilan_id'));
        $paOrders = collect();

        if ($selectedKantorPerwakilan) {
            $paOrders = PaOrder::query()
                ->where('qc_status', PaOrder::QC_STATUS_PASSED)
                ->whereNull('bast_document_id')
                ->whereDoesntHave('bastItems')
                ->where(function ($query) use ($selectedKantorPerwakilan) {
                    $query->where('kantor_perwakilan_id', $selectedKantorPerwakilan->id)
                        ->orWhere(function ($query) use ($selectedKantorPerwakilan) {
                            $query->whereNull('kantor_perwakilan_id')
                                ->whereHas('region', fn ($regionQuery) => $regionQuery
                                    ->where('kantor_perwakilan_id', $selectedKantorPerwakilan->id));
                        });
                })
                ->with('region')
                ->orderBy('pa_number')
                ->get();
        }

        return view('admin.bast.index', compact('documents', 'kantorPerwakilan', 'selectedKantorPerwakilan', 'paOrders'));
    }

    public function preview(BastDocument $bastDocument)
    {
        $relativePath = $this->ensurePdfExists($bastDocument);
        $safeFilename = $this->pdfFilename($bastDocument);
        $absolutePath = Storage::disk('public')->path($relativePath);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function download(BastDocument $bastDocument)
    {
        $relativePath = $this->ensurePdfExists($bastDocument);
        $safeFilename = $this->pdfFilename($bastDocument);
        $absolutePath = Storage::disk('public')->path($relativePath);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'kantor_perwakilan_id' => ['required', 'exists:kantor_perwakilan,id'],
            'pa_ids' => ['required', 'array', 'min:1'],
            'pa_ids.*' => ['required', 'integer', 'distinct', 'exists:pa_orders,id'],
            'notes' => 'nullable|string|max:1000',
            'pihak_menyerahkan' => 'nullable|string|max:150',
            'pihak_menerima' => 'nullable|string|max:150',
            'jabatan_menyerahkan' => 'nullable|string|max:150',
            'jabatan_menerima' => 'nullable|string|max:150',
            'lokasi' => 'nullable|string|max:150',
        ]);

        $kantorPerwakilan = KantorPerwakilan::findOrFail($validated['kantor_perwakilan_id']);
        if (blank($kantorPerwakilan->kode)) {
            return Redirect::route('admin.bast.index', ['kantor_perwakilan_id' => $kantorPerwakilan->id])
                ->withErrors(['kantor_perwakilan_id' => 'Atur kode KP sebelum membuat nomor BAST.']);
        }

        $document = DB::transaction(function () use ($validated, $kantorPerwakilan) {
            $office = KantorPerwakilan::query()->whereKey($kantorPerwakilan->id)->lockForUpdate()->firstOrFail();
            /** @var \Illuminate\Database\Eloquent\Collection<int, PaOrder> $paOrders */
            $paOrders = PaOrder::with('region')
                ->whereIn('id', $validated['pa_ids'])
                ->lockForUpdate()
                ->get();
            $eligibleOrders = $paOrders->filter(function (PaOrder $paOrder) use ($office) {
                $belongsToOffice = (int) $paOrder->kantor_perwakilan_id === (int) $office->id
                    || (blank($paOrder->kantor_perwakilan_id)
                        && (int) $paOrder->region?->kantor_perwakilan_id === (int) $office->id);

                return $belongsToOffice
                    && $paOrder->qc_status === PaOrder::QC_STATUS_PASSED
                    && blank($paOrder->bast_document_id)
                    && ! $paOrder->bastItems()->exists();
            });

            if ($eligibleOrders->count() !== count($validated['pa_ids'])) {
                throw ValidationException::withMessages([
                    'pa_ids' => 'Semua PA harus lolos QC, belum masuk BAST, dan berada pada KP yang dipilih.',
                ]);
            }

            $region = $eligibleOrders->first()->region ?? $office->regions()->first();
            if (! $region) {
                throw ValidationException::withMessages([
                    'kantor_perwakilan_id' => 'KP harus memiliki wilayah agar BAST dapat dibuat.',
                ]);
            }

            $year = now()->year;
            $sequence = $office->bastDocuments()->whereYear('tanggal', $year)->count() + 1;
            $numberFormat = SlaSetting::current()->bast_number_format ?: '{sequence}/BAST/{kp_code}/{year}';
            $nomorBast = str_replace(
                ['{sequence}', '{kp_code}', '{year}'],
                [str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), strtoupper($office->kode), (string) $year],
                $numberFormat
            );

            $document = BastDocument::create([
                'nomor_bast' => $nomorBast,
                'region_id' => $region->id,
                'kantor_perwakilan_id' => $office->id,
                'created_by' => auth()->id(),
                'tanggal' => now()->toDateString(),
                'status' => 'FINAL',
                'notes' => $validated['notes'] ?? null,
                'pihak_menyerahkan' => $validated['pihak_menyerahkan'] ?? 'PT Icon Plus',
                'pihak_menerima' => $validated['pihak_menerima'] ?? 'Pelanggan',
                'jabatan_menyerahkan' => $validated['jabatan_menyerahkan'] ?? 'Manager Operasional',
                'jabatan_menerima' => $validated['jabatan_menerima'] ?? 'PIC Pelanggan',
                'lokasi' => $validated['lokasi'] ?? $office->nama,
            ]);

            foreach ($eligibleOrders as $paOrder) {
                $previousStatus = $paOrder->current_status;
                BastItem::create([
                    'bast_document_id' => $document->id,
                    'pa_id' => $paOrder->id,
                    'serial_number' => $paOrder->serial_number_ont,
                    'location' => $paOrder->region?->kabupaten_kota ?? $office->nama,
                ]);

                $paOrder->update([
                    'bast_document_id' => $document->id,
                    'payment_status' => 'BAST_CREATED',
                    'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
                ]);
                $paOrder->statusLogs()->create([
                    'from_status' => $previousStatus,
                    'to_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
                    'changed_by' => auth()->id(),
                    'note' => 'BAST dibuat: ' . $document->nomor_bast,
                    'changed_at' => now(),
                ]);
            }

            \App\Services\AuditLogger::log(
                'bast_generated',
                'BastDocument',
                $document->id,
                "BAST {$document->nomor_bast} dibuat oleh admin untuk {$eligibleOrders->count()} PA."
            );

            return $document;
        });

        $this->ensurePdfExists($document);

        return Redirect::route('admin.bast.index')->with('success', 'BAST berhasil dibuat untuk ' . $document->items()->count() . ' PA.');
    }

    public function void(Request $request, BastDocument $bastDocument)
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $bastDocument, $validated) {
            $document = BastDocument::query()->whereKey($bastDocument->id)->lockForUpdate()->firstOrFail();
            if ($document->status !== 'FINAL') {
                throw ValidationException::withMessages(['void_reason' => 'Hanya BAST berstatus FINAL yang dapat dibatalkan.']);
            }

            $items = $document->allItems();
            foreach ($items as $item) {
                $paOrder = $item->paOrder;
                if (! $paOrder || (int) $paOrder->bast_document_id !== (int) $document->id) {
                    continue;
                }
                if ($paOrder->payment_status === 'PAID' || $paOrder->current_status === PaOrder::BLUEPRINT_STATUS_PAID) {
                    throw ValidationException::withMessages(['void_reason' => 'BAST tidak dapat dibatalkan karena salah satu PA sudah dibayar.']);
                }

                $activeItem = BastItem::query()
                    ->where('bast_document_id', $document->id)
                    ->where('pa_id', $paOrder->id)
                    ->first();
                if ($activeItem) {
                    \App\Models\BastItemArchive::create([
                        'original_item_id' => $activeItem->id,
                        'bast_document_id' => $activeItem->bast_document_id,
                        'pa_id' => $activeItem->pa_id,
                        'serial_number' => $activeItem->serial_number,
                        'location' => $activeItem->location,
                        'archive_reason' => 'BAST dibatalkan: ' . trim($validated['void_reason']),
                        'archived_at' => now(),
                    ]);
                    $activeItem->delete();
                }

                $previousStatus = $paOrder->current_status;
                $paOrder->update([
                    'bast_document_id' => null,
                    'payment_status' => null,
                    'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_VOID,
                ]);
                $paOrder->statusLogs()->create([
                    'from_status' => $previousStatus,
                    'to_status' => PaOrder::BLUEPRINT_STATUS_BAST_VOID,
                    'changed_by' => $request->user()->id,
                    'note' => "BAST {$document->nomor_bast} dibatalkan: {$validated['void_reason']}",
                    'changed_at' => now(),
                ]);
            }

            $document->update([
                'status' => 'VOID',
                'void_reason' => trim($validated['void_reason']),
                'voided_by' => $request->user()->id,
                'voided_at' => now(),
            ]);

            \App\Services\AuditLogger::log(
                'bast_voided',
                'BastDocument',
                $document->id,
                "BAST {$document->nomor_bast} dibatalkan: {$validated['void_reason']}"
            );
        });

        return Redirect::route('admin.bast.index')->with('success', "BAST {$bastDocument->nomor_bast} dibatalkan dan alasan tercatat.");
    }

    protected function ensurePdfExists(BastDocument $bastDocument): string
    {
        $year = $bastDocument->tanggal ? date('Y', strtotime($bastDocument->tanggal)) : now()->format('Y');
        $relativePath = 'bast/' . $year . '/' . $this->pdfFilename($bastDocument);
        $legacyPath = 'bast/' . $year . '/' . $this->legacyPdfFilename($bastDocument);

        $pdf = $this->buildPdfContent($bastDocument);

        foreach (array_unique([$relativePath, $legacyPath]) as $path) {
            Storage::disk('public')->makeDirectory(dirname($path));
            Storage::disk('public')->put($path, $pdf);
        }

        if (blank($bastDocument->file_url) || $bastDocument->file_url !== $relativePath) {
            $bastDocument->update(['file_url' => $relativePath]);
        }

        return $relativePath;
    }

    protected function pdfFilename(BastDocument $bastDocument): string
    {
        $base = str_replace(['/', '\\'], '_', (string) ($bastDocument->nomor_bast ?? 'bast'));

        return Str::slug($base, '_') . '.pdf';
    }

    protected function legacyPdfFilename(BastDocument $bastDocument): string
    {
        $base = str_replace(['/', '\\'], '', (string) ($bastDocument->nomor_bast ?? 'bast'));

        return Str::slug($base, '') . '.pdf';
    }

    protected function buildPdfContent(BastDocument $bastDocument): string
    {
        $templatePath = storage_path('app/templates/bast-template.pdf');

        if (file_exists($templatePath)) {
            return $this->buildTemplatePdfContent($bastDocument, $templatePath);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderPdfHtml($bastDocument));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    protected function buildTemplatePdfContent(BastDocument $bastDocument, string $templatePath): string
    {
        $pdf = new class('P', 'pt') extends Fpdi {
            public function selectPage(int $page): void
            {
                $this->page = $page;
                $this->SetFillColor(255, 255, 255);
            }
        };
        $pdf->SetAutoPageBreak(false);
        $templatePageCount = $pdf->setSourceFile($templatePath);
        $templatePages = [];

        for ($pageNumber = 1; $pageNumber <= $templatePageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $pageSize = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($pageSize['orientation'], [$pageSize['width'], $pageSize['height']]);
            $pdf->useTemplate($templateId, 0, 0, $pageSize['width'], $pageSize['height']);
            $templatePages[] = $pageSize;
        }

        $items = $bastDocument->allItems();
        $tanggal = $bastDocument->tanggal
            ? \Illuminate\Support\Carbon::parse($bastDocument->tanggal)
            : now();
        $tanggalText = $tanggal->format('d/m/Y');
        $hariText = $tanggal->locale('id')->translatedFormat('l');
        $nomorBast = (string) ($bastDocument->nomor_bast ?? '-');
        $pihakMenyerahkan = (string) ($bastDocument->pihak_menyerahkan ?? 'PT Icon Plus');
        $pihakMenerima = (string) ($bastDocument->pihak_menerima ?? 'Pelanggan');
        $jabatanMenyerahkan = (string) ($bastDocument->jabatan_menyerahkan ?? 'Manager Operasional');
        $jabatanMenerima = (string) ($bastDocument->jabatan_menerima ?? 'PIC Pelanggan');
        $lokasi = (string) ($bastDocument->lokasi ?? $bastDocument->region?->kabupaten_kota ?? '-');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        $whiteout = static function (float $x, float $y, float $width, float $height) use ($pdf): void {
            $pdf->Rect($x, $y, $width, $height, 'F');
        };

        $columns = [95.5, 124.6, 215.6, 320.8, 408.8, 486.8];
        $tableLayouts = [
            ['page' => 1, 'top' => 325.5, 'rows' => 20],
            ['page' => 2, 'top' => 103.5, 'rows' => 9],
        ];

        foreach ($tableLayouts as $layout) {
            if ($layout['page'] > $templatePageCount) {
                continue;
            }

            $pdf->selectPage($layout['page']);
            for ($row = 0; $row <= $layout['rows']; $row++) {
                $top = $layout['top'] + ($row * 15);
                foreach (array_keys($columns) as $columnIndex) {
                    if (! isset($columns[$columnIndex + 1])) {
                        continue;
                    }

                    $whiteout($columns[$columnIndex] + 0.7, $top + 0.7, $columns[$columnIndex + 1] - $columns[$columnIndex] - 1.4, 13.6);
                }
            }
        }

        $pdf->selectPage(1);
        $whiteout(215, 127, 170, 18);
        $whiteout(70, 154, 458, 33);
        $whiteout(105, 195, 220, 34);
        $whiteout(105, 237, 310, 48);

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetXY(219, 130);
        $pdf->Cell(160, 14, $nomorBast, 0, 0, 'C');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(72, 156);
        $pdf->MultiCell(455, 12, 'Pada hari ini, ' . $hariText . ' tanggal ' . $tanggalText . ' telah dilaksanakan serah terima perangkat hasil dismantle dari:', 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetXY(108, 198);
        $pdf->Cell(200, 13, 'PIHAK PERTAMA (Menyerahkan)', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(108, 212);
        $pdf->Cell(210, 13, 'Nama : ' . $pihakMenyerahkan, 0, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetXY(108, 240);
        $pdf->Cell(200, 13, 'PIHAK KEDUA (Menerima)', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(108, 254);
        $pdf->Cell(270, 13, 'Nama : ' . $pihakMenerima, 0, 0, 'L');
        $pdf->SetXY(108, 268);
        $pdf->Cell(300, 13, 'Unit : ' . $lokasi, 0, 0, 'L');

        $headerLabels = ['No', 'ID Pelanggan', 'Serial Number ONT', 'Kondisi ONT', 'Adaptor'];
        $drawTablePage = function (int $pageNumber, float $tableTop, array $pageItems, int $firstIndex, bool $drawTemplateGrid = true) use ($pdf, $columns, $headerLabels): void {
            if ($drawTemplateGrid) {
                $pdf->selectPage($pageNumber);
                $pdf->SetFont('Helvetica', 'B', 8);
                foreach ($headerLabels as $columnIndex => $label) {
                    $pdf->SetXY($columns[$columnIndex] + 1, $tableTop + 1);
                    $pdf->Cell($columns[$columnIndex + 1] - $columns[$columnIndex] - 2, 13, $label, 0, 0, 'C');
                }
            }

            $pdf->selectPage($pageNumber);
            foreach ($pageItems as $index => $item) {
                $rowTop = $tableTop + 15 + ($index * 15);
                $paOrder = $item->paOrder;
                $values = [
                    (string) ($firstIndex + $index + 1),
                    (string) ($paOrder?->customer_id ?? '-'),
                    (string) ($item->serial_number ?? '-'),
                    (string) ($paOrder?->kondisi_ont ?: 'Baik'),
                    (string) ($paOrder?->adaptor ?: 'Ada'),
                ];

                foreach ($values as $columnIndex => $value) {
                    $cellWidth = $columns[$columnIndex + 1] - $columns[$columnIndex] - 2;
                    $fontSize = 9;
                    $pdf->SetFont('Helvetica', '', $fontSize);
                    while ($pdf->GetStringWidth($value) > $cellWidth - 8 && $fontSize > 6) {
                        $fontSize -= 0.5;
                        $pdf->SetFont('Helvetica', '', $fontSize);
                    }

                    $pdf->SetXY($columns[$columnIndex] + 1, $rowTop + 1);
                    $pdf->Cell($cellWidth, 13, $value, 0, 0, $columnIndex === 0 || $columnIndex > 2 ? 'C' : 'L');
                }
            }
        };

        $firstPageItems = $items->slice(0, 20)->all();
        $drawTablePage(1, 325.5, $firstPageItems, 0);

        if ($templatePageCount >= 2) {
            $secondPageItems = $items->slice(20, 9)->all();
            $drawTablePage(2, 103.5, $secondPageItems, 20);

            $pdf->selectPage(2);
            $whiteout(345, 363, 184, 20);
            $whiteout(70, 474, 125, 34);
            $whiteout(368, 474, 130, 34);

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetXY(351, 367);
            $pdf->Cell(176, 13, $lokasi . ', ' . $tanggal->translatedFormat('d F Y'), 0, 0, 'C');

            $pdf->SetXY(74, 479);
            $pdf->Cell(115, 12, $pihakMenyerahkan, 0, 0, 'C');
            $pdf->SetXY(74, 492);
            $pdf->Cell(115, 12, $jabatanMenyerahkan, 0, 0, 'C');
            $pdf->SetXY(373, 479);
            $pdf->Cell(120, 12, $pihakMenerima, 0, 0, 'C');
            $pdf->SetXY(373, 492);
            $pdf->Cell(120, 12, $jabatanMenerima, 0, 0, 'C');
        }

        $remainingItems = $items->slice(29)->values();
        while ($remainingItems->isNotEmpty()) {
            $pdf->AddPage('P', [$templatePages[0]['width'], $templatePages[0]['height']]);
            $tableTop = 100;
            $rowCapacity = 30;
            $pageItems = $remainingItems->take($rowCapacity)->all();
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.5);
            for ($row = 0; $row <= count($pageItems); $row++) {
                $y = $tableTop + ($row * 15);
                $pdf->Line($columns[0], $y, $columns[5], $y);
            }
            foreach ($columns as $column) {
                $pdf->Line($column, $tableTop, $column, $tableTop + ((count($pageItems) + 1) * 15));
            }
            $drawTablePage($pdf->PageNo(), $tableTop, $pageItems, 29);
            $remainingItems = $remainingItems->slice(count($pageItems))->values();
        }

        if ($bastDocument->status === 'VOID') {
            $pageCount = $pdf->PageNo();
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $pdf->selectPage($pageNumber);
                $pdf->SetTextColor(190, 35, 35);
                $pdf->SetFont('Helvetica', 'B', 22);
                $pdf->SetXY(430, 22);
                $pdf->Cell(135, 24, 'VOID', 0, 0, 'R');
            }
        }

        return $pdf->Output('S');
    }

    protected function renderPdfHtml(BastDocument $bastDocument): string
    {
        $lines = $bastDocument->toPdfText();

        $rows = implode("\n", array_map(function ($line) {
            return '<div>' . e($line) . '</div>';
        }, explode("\n", $lines)));

        return '<html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; line-height: 1.5; }
            .page { padding: 32px 32px 40px; }
            h1 { font-size: 20px; margin: 0 0 12px; text-align: center; }
            .muted { color: #4b5563; }
            .section { margin-top: 10px; }
            .label { font-weight: 700; display: inline-block; width: 160px; }
            .line { margin-bottom: 6px; }
        </style></head><body><div class="page"><h1>BERITA ACARA SERAH TERIMA (BAST)</h1>' . $rows . '</div></body></html>';
    }
}
