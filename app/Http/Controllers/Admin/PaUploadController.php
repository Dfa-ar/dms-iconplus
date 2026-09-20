<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessPaUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadPaRequest;
use App\Models\UploadBatch;
use App\Services\PaUploadService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PaUploadController extends Controller
{
    public function create()
    {
        $this->authorize('uploadPa');

        return view('admin.pa.upload');
    }

    public function template()
    {
        $this->authorize('uploadPa');

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'pa_number', 'customer_id', 'customer_name', 'contact_phone',
                'address', 'kabupaten_kota', 'kecamatan', 'kelurahan', 'pa_date',
            ]);
            fclose($handle);
        }, 'template-upload-pa.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(UploadPaRequest $request)
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_shift($rows) ?? [];
        $mapping = app(PaUploadService::class)->mapColumns($headers);

        return view('admin.pa.upload', [
            'previewHeaders' => $headers,
            'previewRows' => array_slice($rows, 0, 10),
            'mapping' => $mapping,
            'requiredColumns' => ['pa_number', 'customer_name', 'kabupaten_kota', 'pa_date'],
        ]);
    }

    public function store(UploadPaRequest $request)
    {
        $file = $request->file('file');

        $batch = UploadBatch::create([
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        $path = $file->store('upload-sources', 'local');
        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
        $rowCount = max($spreadsheet->getActiveSheet()->getHighestDataRow() - 1, 0);
        unset($spreadsheet);

        if ($rowCount > 1000) {
            ProcessPaUpload::dispatch($batch->id, $path);

            return back()->with('status', "Upload {$rowCount} baris masuk antrean pemrosesan. Hasil akan tersedia setelah worker queue selesai.");
        }

        $result = app(PaUploadService::class)->process($batch, $path);
        Storage::disk('local')->delete($path);

        return back()->with('status', sprintf(
            '%d dari %d baris berhasil diimpor. %d diperbarui, %d gagal.',
            $result['success'],
            $result['total'],
            $result['updated'],
            $result['failed']
        ));
    }
}
