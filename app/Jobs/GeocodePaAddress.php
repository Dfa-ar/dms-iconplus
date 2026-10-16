<?php

namespace App\Jobs;

use App\Contracts\AddressGeocoder;
use App\Exceptions\GeocodingConfigurationException;
use App\Models\PaOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GeocodePaAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $paOrderId) {}

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(AddressGeocoder $geocoder): void
    {
        $paOrder = PaOrder::with('region')->find($this->paOrderId);
        if (! $paOrder) {
            return;
        }

        if (is_numeric($paOrder->latitude) && is_numeric($paOrder->longitude)) {
            return;
        }

        if (blank($paOrder->address)) {
            $this->markFailed($paOrder, 'Alamat pelanggan belum diisi.');

            return;
        }

        try {
            $coordinates = $geocoder->geocode($paOrder);
        } catch (GeocodingConfigurationException $exception) {
            $this->markFailed($paOrder, $exception->getMessage());

            return;
        }

        if ($coordinates === null) {
            $this->markFailed($paOrder, 'Alamat tidak menghasilkan koordinat. Periksa alamat atau isi koordinat secara manual.');

            return;
        }

        $paOrder->update([
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'geocode_status' => PaOrder::GEOCODE_STATUS_OK,
            'geocode_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $paOrder = PaOrder::find($this->paOrderId);
        if (! $paOrder || (is_numeric($paOrder->latitude) && is_numeric($paOrder->longitude))) {
            return;
        }

        $this->markFailed($paOrder, $exception?->getMessage() ?: 'Pemrosesan geocoding gagal setelah beberapa percobaan.');
    }

    private function markFailed(PaOrder $paOrder, string $reason): void
    {
        $paOrder->update([
            'geocode_status' => PaOrder::GEOCODE_STATUS_FAILED,
            'geocode_error' => mb_substr($reason, 0, 2000),
        ]);
    }
}