<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapBlueprintSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_shows_all_west_java_cities_and_regencies_without_kp_records(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->seed(\Database\Seeders\RegionSeeder::class);

        $response = $this->actingAs($admin)->getJson(route('admin.map.summary'));
        $markers = $response->json('data');

        $response->assertOk();
        $this->assertCount(27, $markers);
        $this->assertContains('Kab. Pangandaran', array_column($markers, 'name'));
        $this->assertContains('Kota Depok', array_column($markers, 'name'));
        $this->assertSame(['region'], array_values(array_unique(array_column($markers, 'kind'))));
        $this->assertNotNull($markers[0]['lat']);
        $this->assertNotNull($markers[0]['lng']);
    }

    public function test_map_summary_uses_kantor_perwakilan_as_primary_blueprint_marker(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $kp = KantorPerwakilan::create([
            'nama' => 'Kantor Perwakilan Tasikmalaya',
            'alamat' => 'Jl. Cikuray No. 1',
            'latitude' => -7.3274,
            'longitude' => 108.2207,
            'pic_nama' => 'Rahmat',
            'pic_unit' => 'Operasional',
        ]);

        $office = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'office_name' => 'Kantor Perwakilan Tasikmalaya',
            'office_code' => 'KP-TSM',
            'level' => 'office',
            'is_office' => true,
            'parent_group' => 'Tasikmalaya',
            'kantor_perwakilan_id' => $kp->id,
            'lat' => -7.3274,
            'lng' => 108.2207,
        ]);

        $area = Region::create([
            'kabupaten_kota' => 'Kota Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
            'parent_region_id' => $office->id,
            'kantor_perwakilan_id' => $kp->id,
            'is_office' => false,
        ]);
        Region::create([
            'kabupaten_kota' => 'Kota Tasikmalaya',
            'parent_group' => 'Tasikmalaya',
            'is_office' => false,
            'lat' => -7.3274,
            'lng' => 108.2207,
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'petugas'])->id, 'is_active' => true])->id,
            'region_id' => $office->id,
            'kantor_perwakilan_id' => $kp->id,
            'is_active' => true,
        ]);

        PaOrder::factory()->create([
            'region_id' => $area->id,
            'kantor_perwakilan_id' => $kp->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.map.summary'));
        $payload = $response->json('data');

        $this->assertNotEmpty($payload);
        $this->assertSame('kp', $payload[0]['kind']);
        $this->assertSame('Kantor Perwakilan Tasikmalaya', $payload[0]['name']);
        $this->assertSame(-7.3274, (float) $payload[0]['lat']);
        $this->assertSame(108.2207, (float) $payload[0]['lng']);
        $this->assertSame(1, $payload[0]['officer_count']);
        $this->assertSame(1, $payload[0]['total_pa']);
        $this->assertSame(route('admin.assignments.index', ['region_id' => $area->id]), $payload[0]['assignment_route']);
        $this->assertArrayHasKey('status', $payload[0]);
        $this->assertSame('Kantor Perwakilan Tasikmalaya', $payload[0]['display_name']);

        $summary = $this->actingAs($admin)->getJson(route('admin.map.summary'));
        $this->assertArrayHasKey('clusters', $summary->json());
        $this->assertCount(1, $summary->json('officers'));
        $this->assertSame(1, $summary->json('heatmap.0.2'));
        $this->assertNotEmpty($summary->json('clusters'));
        $this->assertArrayHasKey('active_officer_count', $summary->json('clusters')[0]);
        $this->assertSame(1, $summary->json('clusters')[0]['active_officer_count']);
    }
}
