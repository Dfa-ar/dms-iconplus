<?php

use App\Console\Commands\GenerateDummyEvidenceCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dummy:evidence {--pa=} {--count=10} {--force}', function () {
    $this->call(GenerateDummyEvidenceCommand::class, [
        '--pa' => $this->option('pa'),
        '--count' => $this->option('count'),
        '--force' => $this->option('force'),
    ]);
})->purpose('Generate dummy photo evidence for QC preview');
