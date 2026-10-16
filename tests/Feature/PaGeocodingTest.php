<?php

namespace Tests\Feature;

use App\Jobs\GeocodePaAddress;
use App\Models\PaOrder;
use App\Models\Officer;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaGeocodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocode_job_saves_google_coordinates_for_pa_address(): void
    {
        config(['services.google_maps.key' => 'test-geocoding-key']);
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => ['location' => ['lat' => -6.9123456, 'lng' => 107.6123456]],
                ]],
            ]),
        ]);

        $region = Region::create(['kabupaten_kota' => 'Bandung', 'kecamatan' => 'Coblong', 'kelurahan' => 'Dago']);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'address' => 'Jalan Ir. H. Juanda 100',
            'geocode_status' => PaOrder::GEOCODE_STATUS_PENDING,
        ]);

        GeocodePaAddress::dispatchSync($paOrder->id);

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'latitude' => -6.9123456,
            'longitude' => 107.6123456,
            'geocode_status' => PaOrder::GEOCODE_STATUS_OK,
            'geocode_error' => null,
        ]);
        Http::assertSent(fn (ClientRequest $request) => $request['key'] === 'test-geocoding-key'
            && str_contains($request['address'], 'Jalan Ir. H. Juanda 100')
            && str_contains($request['address'], 'Bandung'));
    }

    public function test_geocode_job_marks_unmatched_address_failed(): void
    {
        config(['services.google_maps.key' => 'test-geocoding-key']);
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS'])]);
        $paOrder = PaOrder::factory()->create([
            'address' => 'Alamat yang tidak dikenal',
            'geocode_status' => PaOrder::GEOCODE_STATUS_PENDING,
        ]);

        GeocodePaAddress::dispatchSync($paOrder->id);

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'geocode_status' => PaOrder::GEOCODE_STATUS_FAILED,
        ]);
        $this->assertStringContainsString('tidak menghasilkan koordinat', $paOrder->fresh()->geocode_error);
    }

    public function test_geocode_job_exposes_missing_api_key_as_actionable_failure(): void
    {
        config(['services.google_maps.key' => null]);
        Http::fake();
        $paOrder = PaOrder::factory()->create([
            'address' => 'Jalan Asia Afrika 1, Bandung',
            'geocode_status' => PaOrder::GEOCODE_STATUS_PENDING,
        ]);

        GeocodePaAddress::dispatchSync($paOrder->id);

        $this->assertSame(PaOrder::GEOCODE_STATUS_FAILED, $paOrder->fresh()->geocode_status);
        $this->assertStringContainsString('GOOGLE_MAPS_API_KEY', $paOrder->fresh()->geocode_error);
        Http::assertNothingSent();
    }

    public function test_admin_can_save_manual_coordinates_but_petugas_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $petugas = $this->userWithRole('petugas');
        $paOrder = PaOrder::factory()->create([
            'latitude' => null,
            'longitude' => null,
            'geocode_status' => PaOrder::GEOCODE_STATUS_FAILED,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.pa.coordinates', $paOrder), ['latitude' => -6.9, 'longitude' => 107.6])
            ->assertRedirect()
            ->assertSessionHas('status', 'Koordinat pelanggan berhasil disimpan.');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'latitude' => -6.9,
            'longitude' => 107.6,
            'geocode_status' => PaOrder::GEOCODE_STATUS_OK,
            'geocode_error' => null,
        ]);

        $this->actingAs($petugas)
            ->patch(route('admin.pa.coordinates', $paOrder), ['latitude' => -6.8, 'longitude' => 107.5])
            ->assertForbidden();
    }

    public function test_invalid_manual_coordinates_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $paOrder = PaOrder::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.pa.show', $paOrder))
            ->patch(route('admin.pa.coordinates', $paOrder), ['latitude' => -91, 'longitude' => 181])
            ->assertRedirect(route('admin.pa.show', $paOrder))
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_admin_can_retry_geocoding_for_failed_pa(): void
    {
        Bus::fake();
        $admin = $this->userWithRole('admin');
        $paOrder = PaOrder::factory()->create([
            'address' => 'Jalan Asia Afrika 1, Bandung',
            'geocode_status' => PaOrder::GEOCODE_STATUS_FAILED,
            'geocode_error' => 'Alamat tidak menghasilkan koordinat.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.pa.geocode', $paOrder))
            ->assertRedirect()
            ->assertSessionHas('status', 'Geocoding PA masuk antrean. Muat ulang halaman setelah worker selesai.');

        $this->assertSame(PaOrder::GEOCODE_STATUS_PENDING, $paOrder->fresh()->geocode_status);
        $this->assertNull($paOrder->fresh()->geocode_error);
        Bus::assertDispatched(GeocodePaAddress::class, fn (GeocodePaAddress $job) => $job->paOrderId === $paOrder->id);
    }

    public function test_backfill_command_queues_only_existing_pa_without_coordinates(): void
    {
        config(['services.google_maps.key' => 'test-geocoding-key']);
        Bus::fake();
        $missingCoordinates = PaOrder::factory()->create([
            'address' => 'Jalan Asia Afrika 1, Bandung',
            'latitude' => null,
            'longitude' => null,
        ]);
        PaOrder::factory()->create([
            'address' => 'Jalan Merdeka 1, Bandung',
            'latitude' => -6.9,
            'longitude' => 107.6,
        ]);
        PaOrder::factory()->create(['address' => null]);

        $this->artisan('pa:geocode-missing')
            ->expectsOutput('1 PA masuk antrean geocoding.')
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(GeocodePaAddress::class, 1);
        Bus::assertDispatched(GeocodePaAddress::class, fn (GeocodePaAddress $job) => $job->paOrderId === $missingCoordinates->id);
    }

    public function test_backfill_command_requires_google_api_key(): void
    {
        config(['services.google_maps.key' => null]);
        Bus::fake();
        PaOrder::factory()->create(['address' => 'Jalan Asia Afrika 1, Bandung']);

        $this->artisan('pa:geocode-missing')
            ->expectsOutput('GOOGLE_MAPS_API_KEY belum dikonfigurasi.')
            ->assertExitCode(1);

        Bus::assertNothingDispatched();
    }

    public function test_petugas_route_uses_customer_coordinates_before_region_fallback(): void
    {
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $user = User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true]);
        $officer = Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $region = Region::create(['kabupaten_kota' => 'Bandung', 'lat' => -6.9, 'lng' => 107.6]);
        $nearPa = PaOrder::factory()->create([
            'pa_number' => 'PA-GEOCODE-NEAR',
            'region_id' => $region->id,
            'latitude' => -6.9001,
            'longitude' => 107.6,
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $officer->id,
            'assigned_date' => now()->toDateString(),
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-GEOCODE-FAR',
            'region_id' => $region->id,
            'latitude' => -6.95,
            'longitude' => 107.6,
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $officer->id,
            'assigned_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('petugas.tasks.index', ['lat' => -6.9, 'lng' => 107.6]))
            ->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->first()?->is($nearPa));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }
}