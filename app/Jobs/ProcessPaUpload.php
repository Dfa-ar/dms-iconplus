<?php

namespace App\Jobs;

use App\Models\UploadBatch;
use App\Services\PaUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessPaUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $batchId,
        public string $filePath,
    ) {}

    public function handle(PaUploadService $service): void
    {
        $batch = UploadBatch::findOrFail($this->batchId);

        try {
            $service->process($batch, $this->filePath);
        } finally {
            Storage::disk('local')->delete($this->filePath);
        }
    }
}
