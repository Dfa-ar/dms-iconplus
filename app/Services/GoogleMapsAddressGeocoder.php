<?php

namespace App\Services;

use App\Contracts\AddressGeocoder;
use App\Exceptions\GeocodingConfigurationException;
use App\Models\PaOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleMapsAddressGeocoder implements AddressGeocoder
{
    public function geocode(PaOrder $paOrder): ?array
    {
        $apiKey = config('services.google_maps.key');

        if (blank($apiKey)) {
            throw new GeocodingConfigurationException('GOOGLE_MAPS_API_KEY belum dikonfigurasi.');
        }

        $region = $paOrder->region;
        $address = collect([
            $paOrder->address,
            $region?->kelurahan,
            $region?->kecamatan,
            $region?->kabupaten_kota,
            'Indonesia',
        ])->filter(fn ($part) => filled($part))->implode(', ');

        if (blank($paOrder->address)) {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get(config('services.google_maps.geocoding_url'), [
                'address' => $address,
                'key' => $apiKey,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Layanan geocoding tidak dapat dihubungi.');
        }

        $payload = $response->json();
        $status = $payload['status'] ?? 'UNKNOWN_ERROR';

        if ($status === 'ZERO_RESULTS') {
            return null;
        }

        if ($status !== 'OK') {
            throw new RuntimeException('Layanan geocoding mengembalikan status ' . $status . '.');
        }

        $location = $payload['results'][0]['geometry']['location'] ?? null;
        if (! is_array($location)
            || ! is_numeric($location['lat'] ?? null)
            || ! is_numeric($location['lng'] ?? null)) {
            throw new RuntimeException('Layanan geocoding tidak mengembalikan koordinat yang valid.');
        }

        $latitude = (float) $location['lat'];
        $longitude = (float) $location['lng'];

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('Layanan geocoding mengembalikan koordinat di luar rentang.');
        }

        return ['lat' => $latitude, 'lng' => $longitude];
    }
}