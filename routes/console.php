<?php

use App\Console\Commands\GenerateDummyEvidenceCommand;
use App\Jobs\GeocodePaAddress;
use App\Models\PaOrder;
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

Artisan::command('pa:geocode-missing', function () {
    if (blank(config('services.google_maps.key'))) {
        $this->error('GOOGLE_MAPS_API_KEY belum dikonfigurasi.');

        return 1;
    }

    $queued = 0;
    PaOrder::query()
        ->whereNotNull('address')
        ->where('address', '!=', '')
        ->where(function ($query) {
            $query->whereNull('latitude')->orWhereNull('longitude');
        })
        ->select(['id'])
        ->chunkById(200, function ($paOrders) use (&$queued) {
            foreach ($paOrders as $paOrder) {
                PaOrder::whereKey($paOrder->id)->update([
                    'geocode_status' => PaOrder::GEOCODE_STATUS_PENDING,
                    'geocode_error' => null,
                ]);
                GeocodePaAddress::dispatch($paOrder->id);
                $queued++;
            }
        });

    $this->info("{$queued} PA masuk antrean geocoding.");

    return 0;
})->purpose('Antrekan geocoding untuk PA lama yang memiliki alamat tetapi belum memiliki koordinat');
