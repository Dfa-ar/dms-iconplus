<?php

namespace App\Services;

use App\Models\PaOrder;
use App\Models\Region;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PaUploadService
{
    /** @return array{total:int, success:int, updated:int, failed:int} */
    public function process(UploadBatch $batch, string $filePath): array
    {
        $spreadsheet = IOFactory::load(Storage::disk('local')->path($filePath));
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_shift($rows) ?? [];
        $mapping = $this->mapColumns($headers);
        $requiredColumns = ['pa_number', 'customer_name', 'kabupaten_kota', 'pa_date'];
        $missingColumns = array_values(array_diff($requiredColumns, array_keys($mapping)));

        if ($missingColumns) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.',
            ]);
        }

        $totalRows = 0;
        $successRows = 0;
        $updatedRows = [];
        $failedRows = [];

        DB::transaction(function () use ($rows, $mapping, $batch, &$totalRows, &$successRows, &$updatedRows, &$failedRows) {
            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $totalRows++;
                $lineNumber = $index + 2;
                $value = fn (string $column) => $row[$mapping[$column]] ?? null;
                $paNumber = $value('pa_number');
                $customerId = $value('customer_id');
                $customerName = $value('customer_name');
                $contactPhone = $value('contact_phone');
                $address = $value('address');
                $kabupatenKota = $value('kabupaten_kota');
                $kecamatan = $value('kecamatan');
                $kelurahan = $value('kelurahan');
                $paDate = $value('pa_date');

                if (empty($paNumber) || empty($customerName) || empty($kabupatenKota) || empty($paDate)) {
                    $failedRows[] = "Baris {$lineNumber}: pa_number/nama pelanggan/wilayah/tanggal PA kosong.";
                    continue;
                }

                $region = Region::firstOrCreate([
                    'kabupaten_kota' => trim($kabupatenKota),
                    'kecamatan' => $kecamatan ? trim($kecamatan) : null,
                    'kelurahan' => $kelurahan ? trim($kelurahan) : null,
                ]);
                $paData = [
                    'pa_number' => trim($paNumber),
                    'customer_id' => $customerId,
                    'customer_name' => trim($customerName),
                    'contact_phone' => $contactPhone,
                    'address' => $address,
                    'region_id' => $region->id,
                    'pa_date' => $paDate,
                ];
                $existing = PaOrder::where('pa_number', trim($paNumber))->first();

                if ($existing) {
                    $existing->update($paData);
                    $updatedRows[] = "Baris {$lineNumber}: PA {$paNumber} diperbarui tanpa mengubah status aktif.";
                } else {
                    PaOrder::create($paData + [
                        'current_status' => PaOrder::STATUS_UNASSIGNED,
                        'batch_id' => $batch->id,
                    ]);
                }

                $successRows++;
            }
        });

        $reportLines = array_merge($updatedRows, $failedRows);
        $reportPath = null;
        if ($reportLines) {
            $reportPath = "upload-reports/batch-{$batch->id}.txt";
            Storage::disk('local')->put($reportPath, implode("\n", $reportLines));
        }

        $batch->update([
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => count($failedRows),
            'error_report_path' => $reportPath,
        ]);

        return [
            'total' => $totalRows,
            'success' => $successRows,
            'updated' => count($updatedRows),
            'failed' => count($failedRows),
        ];
    }

    /** @param array<int, mixed> $headers @return array<string, int> */
    public function mapColumns(array $headers): array
    {
        $aliases = [
            'pa_id' => 'pa_number',
            'nomor_pa' => 'pa_number',
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
