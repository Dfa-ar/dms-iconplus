<?php

namespace App\Services;

use App\Models\PaOrder;
use App\Models\Region;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class PaUploadService
{
    /** @return array{headers:array<int, mixed>, rows:array<int, array<int, mixed>>, mapping:array<string, int>} */
    public function previewFile(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $worksheetInfo = $reader->listWorksheetInfo($filePath)[0] ?? ['totalRows' => 0, 'lastColumnLetter' => 'A'];
        $lastColumn = $worksheetInfo['lastColumnLetter'] ?: 'A';
        $lastRow = min(max((int) $worksheetInfo['totalRows'], 1), 11);
        $reader->setReadFilter($this->readFilter(1, $lastRow));

        $spreadsheet = $reader->load($filePath);
        $rows = $spreadsheet->getActiveSheet()->rangeToArray(
            "A1:{$lastColumn}{$lastRow}",
            null,
            true,
            true,
            false
        );
        $spreadsheet->disconnectWorksheets();

        $headers = array_shift($rows) ?? [];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'mapping' => $this->mapColumns($headers),
        ];
    }

    /** @return array{total:int, success:int, updated:int, failed:int} */
    public function process(UploadBatch $batch, string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);
        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $worksheetInfo = $reader->listWorksheetInfo($fullPath)[0] ?? ['totalRows' => 0, 'lastColumnLetter' => 'A'];
        $lastRow = (int) $worksheetInfo['totalRows'];
        $lastColumn = $worksheetInfo['lastColumnLetter'] ?: 'A';

        $reader->setReadFilter($this->readFilter(1, 1));
        $headerBook = $reader->load($fullPath);
        $headers = $headerBook->getActiveSheet()->rangeToArray("A1:{$lastColumn}1", null, true, true, false)[0] ?? [];
        $headerBook->disconnectWorksheets();
        unset($headerBook);

        $mapping = $this->mapColumns($headers);
        $requiredColumns = ['pa_number', 'customer_id', 'id_pln', 'customer_name', 'kabupaten_kota', 'pa_date'];
        $missingColumns = array_values(array_diff($requiredColumns, array_keys($mapping)));

        if ($missingColumns) {
            $reportPath = "upload-reports/batch-{$batch->id}.txt";
            Storage::disk('local')->put($reportPath, 'Kolom wajib tidak ditemukan: ' . implode(', ', $missingColumns) . '.');
            $rowCount = max($lastRow - 1, 0);
            $batch->update([
                'total_rows' => $rowCount,
                'success_rows' => 0,
                'failed_rows' => max($rowCount, 1),
                'error_report_path' => $reportPath,
                'status' => 'failed',
            ]);

            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.',
            ]);
        }

        $totalRows = 0;
        $successRows = 0;
        $failedRows = [];
        $chunkSize = 500;

        $batch->update([
            'status' => 'processing',
            'total_rows' => max($lastRow - 1, 0),
            'success_rows' => 0,
            'failed_rows' => 0,
            'error_report_path' => null,
        ]);

        for ($startRow = 2; $startRow <= $lastRow; $startRow += $chunkSize) {
            $endRow = min($startRow + $chunkSize - 1, $lastRow);
            $reader->setReadFilter($this->readFilter($startRow, $endRow));
            $spreadsheet = $reader->load($fullPath);
            $rows = $spreadsheet->getActiveSheet()->rangeToArray(
                "A{$startRow}:{$lastColumn}{$endRow}",
                null,
                true,
                true,
                false
            );
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            DB::transaction(function () use ($rows, $startRow, $mapping, $batch, &$totalRows, &$successRows, &$failedRows) {
                foreach ($rows as $offset => $row) {
                    if (empty(array_filter($row, fn ($value) => $value !== null && $value !== ''))) {
                        continue;
                    }

                    $totalRows++;
                    $lineNumber = $startRow + $offset;
                    $value = fn (string $column) => $row[$mapping[$column]] ?? null;
                    $paNumber = $value('pa_number');
                    $customerId = $value('customer_id');
                    $idPln = $value('id_pln');
                    $customerName = $value('customer_name');
                    $contactPhone = $value('contact_phone');
                    $address = $value('address');
                    $kabupatenKota = $value('kabupaten_kota');
                    $kecamatan = $value('kecamatan');
                    $kelurahan = $value('kelurahan');
                    $paDate = $value('pa_date');

                    if (empty($paNumber) || empty($customerId) || empty($idPln) || empty($customerName) || empty($kabupatenKota) || empty($paDate)) {
                        $failedRows[] = "Baris {$lineNumber}: pa_number/ID pelanggan/ID PLN/nama pelanggan/wilayah/tanggal PA wajib diisi.";
                        continue;
                    }

                    if (PaOrder::where('pa_number', trim($paNumber))->exists()) {
                        $failedRows[] = "Baris {$lineNumber}: nomor PA {$paNumber} sudah terdaftar; baris ditolak.";
                        continue;
                    }
                    $region = Region::firstOrCreate([
                        'kabupaten_kota' => trim($kabupatenKota),
                        'kecamatan' => $kecamatan ? trim($kecamatan) : null,
                        'kelurahan' => $kelurahan ? trim($kelurahan) : null,
                    ]);

                    PaOrder::create([
                        'pa_number' => trim($paNumber),
                        'customer_id' => trim($customerId),
                        'id_pln' => trim($idPln),
                        'customer_name' => trim($customerName),
                        'contact_phone' => $contactPhone,
                        'address' => $address,
                        'region_id' => $region->id,
                        'pa_date' => $paDate,
                        'current_status' => PaOrder::STATUS_UNASSIGNED,
                        'batch_id' => $batch->id,
                    ]);
                    $successRows++;
                }
            });

            $batch->update([
                'total_rows' => $totalRows,
                'success_rows' => $successRows,
                'failed_rows' => count($failedRows),
            ]);
        }
        if ($failedRows) {
            $reportPath = "upload-reports/batch-{$batch->id}.txt";
            Storage::disk('local')->put($reportPath, implode("\n", $failedRows));
        } else {
            $reportPath = null;
        }

        $batch->update([
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => count($failedRows),
            'error_report_path' => $reportPath,
            'status' => 'completed',
        ]);

        return [
            'total' => $totalRows,
            'success' => $successRows,
            'updated' => 0,
            'failed' => count($failedRows),
        ];
    }

    private function readFilter(int $startRow, int $endRow): IReadFilter
    {
        return new class($startRow, $endRow) implements IReadFilter
        {
            public function __construct(private int $startRow, private int $endRow) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row <= $this->endRow;
            }
        };
    }

    /** @param array<int, mixed> $headers @return array<string, int> */
    public function mapColumns(array $headers): array
    {
        $aliases = [
            'pa_id' => 'pa_number',
            'nomor_pa' => 'pa_number',
            'id_pelanggan' => 'customer_id',
            'nama_pelanggan' => 'customer_name',
            'kabupaten' => 'kabupaten_kota',
            'kota' => 'kabupaten_kota',
            'tanggal_pa' => 'pa_date',
        ];
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalized = trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim((string) $header)))), '_');
            $normalized = $aliases[$normalized] ?? $normalized;

            if ($normalized !== '') {
                $mapping[$normalized] = $index;
            }
        }

        return $mapping;
    }
}
