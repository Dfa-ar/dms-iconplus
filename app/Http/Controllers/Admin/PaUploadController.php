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

    public function history()
    {
        $this->authorize('uploadPa');

        $uploadBatches = UploadBatch::with('uploader')
            ->latest('uploaded_at')
            ->paginate(20);

        return view('admin.pa.upload-history', compact('uploadBatches'));
    }

    public function downloadErrors(UploadBatch $uploadBatch)
    {
        $this->authorize('uploadPa');

        abort_unless(
            $uploadBatch->error_report_path && Storage::disk('local')->exists($uploadBatch->error_report_path),
            404
        );

        return response()->download(
            Storage::disk('local')->path($uploadBatch->error_report_path),
            'laporan-error-upload-' . $uploadBatch->id . '.txt'
        );
    }

    public function template()
    {
        $this->authorize('uploadPa');

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'pa_number', 'customer_id', 'id_pln', 'customer_name', 'contact_phone',
                'address', 'kabupaten_kota', 'kecamatan', 'kelurahan', 'pa_date',
            ]);
            fclose($handle);
        }, 'template-upload-pa.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(UploadPaRequest $request)
    {
        $preview = app(PaUploadService::class)->previewFile($request->file('file')->getRealPath());

        return view('admin.pa.upload', [
            'previewHeaders' => $preview['headers'],
            'previewRows' => $preview['rows'],
            'mapping' => $preview['mapping'],
            'requiredColumns' => ['pa_number', 'customer_id', 'id_pln', 'customer_name', 'kabupaten_kota', 'pa_date'],
        ]);
    }

    public function store(UploadPaRequest $request)
    {
        $file = $request->file('file');

        $batch = UploadBatch::create([
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'status' => 'queued',
        ]);

        $path = $file->store('upload-sources', 'local');
        $reader = IOFactory::createReaderForFile(Storage::disk('local')->path($path));
        $worksheetInfo = $reader->listWorksheetInfo(Storage::disk('local')->path($path))[0] ?? ['totalRows' => 0];
        $rowCount = max((int) $worksheetInfo['totalRows'] - 1, 0);

        if ($rowCount > 1000) {
            ProcessPaUpload::dispatch($batch->id, $path);

            return back()->with('status', "Upload {$rowCount} baris masuk antrean pemrosesan. Hasil akan tersedia setelah worker queue selesai.");
        }

        try {
            $result = app(PaUploadService::class)->process($batch, $path);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()->with('status', sprintf(
            '%d dari %d baris berhasil diimpor. %d diperbarui, %d gagal.',
            $result['success'],
            $result['total'],
            $result['updated'],
            $result['failed']
        ));
    }
}
