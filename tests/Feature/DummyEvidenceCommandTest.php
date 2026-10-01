<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DummyEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_qc_ready_evidence_for_pa(): void
    {
        $this->seed(RegionSeeder::class);
        Storage::fake('public');

        $paOrder = PaOrder::factory()->done()->create();

        $this->artisan('dummy:evidence', [
            '--pa' => $paOrder->id,
            '--count' => 1,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertEqualsCanonicalizing([
            'k3_awal',
            'ont_depan',
            'ont_belakang_sn',
            'fat_terdekat',
            'kwh_meter',
            'k3_akhir',
            'ba_pengambilan_perangkat',
        ], $paOrder->fresh()->evidences()->pluck('type')->all());
    }
}
