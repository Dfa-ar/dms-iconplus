<?php

namespace Tests\Feature;

use App\Models\KantorPerwakilan;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\KantorPerwakilanDemoSeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionOfficeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_seeder_includes_all_west_java_cities_and_regencies_idempotently(): void
    {
        $this->seed(RegionSeeder::class);
        $this->seed(RegionSeeder::class);

        $this->assertSame(27, Region::query()->count());
        $this->assertDatabaseHas('regions', ['kabupaten_kota' => 'Kab. Pangandaran']);
        $this->assertDatabaseHas('regions', ['kabupaten_kota' => 'Kota Depok']);
        $this->assertDatabaseHas('regions', ['kabupaten_kota' => 'Kab. Bekasi']);

        $expectedGroups = [
            'KP Bandung' => [
                'Kab. Bandung',
                'Kab. Bandung Barat',
                'Kab. Bogor',
                'Kab. Cianjur',
                'Kab. Karawang',
                'Kab. Purwakarta',
                'Kab. Subang',
                'Kab. Sukabumi',
                'Kota Bandung',
                'Kota Bogor',
                'Kota Cimahi',
                'Kota Sukabumi',
            ],
            'KP Cirebon' => [
                'Kab. Cirebon',
                'Kab. Indramayu',
                'Kab. Kuningan',
                'Kab. Majalengka',
                'Kab. Sumedang',
                'Kota Cirebon',
            ],
            'KP Tasikmalaya' => [
                'Kab. Bekasi',
                'Kab. Ciamis',
                'Kab. Garut',
                'Kab. Pangandaran',
                'Kab. Tasikmalaya',
                'Kota Banjar',
                'Kota Bekasi',
                'Kota Depok',
                'Kota Tasikmalaya',
            ],
        ];

        foreach ($expectedGroups as $parentGroup => $expectedRegions) {
            $this->assertSame(
                $expectedRegions,
                Region::where('parent_group', $parentGroup)->orderBy('kabupaten_kota')->pluck('kabupaten_kota')->all()
            );
        }
    }

    public function test_region_seeder_corrects_existing_kp_groups_without_changing_region_links(): void
    {
        $office = KantorPerwakilan::create([
            'nama' => 'KP Karawang',
            'kode' => 'KRG',
        ]);
        $region = Region::create([
            'kabupaten_kota' => 'Kab. Karawang',
            'parent_group' => 'Purwasuka',
            'kantor_perwakilan_id' => $office->id,
        ]);

        $this->seed(RegionSeeder::class);

        $this->assertSame('KP Bandung', $region->fresh()->parent_group);
        $this->assertSame($office->id, $region->fresh()->kantor_perwakilan_id);
    }

    public function test_demo_kp_seeder_consolidates_legacy_dummy_offices_and_preserves_pa_links(): void
    {
        $this->seed(RegionSeeder::class);

        $legacyOffices = [
            'DUMMY-BDG' => 'Kab. Bandung',
            'DUMMY-BGR' => 'Kab. Bogor',
            'DUMMY-BKS' => 'Kab. Bekasi',
            'DUMMY-PWK' => 'Kab. Karawang',
            'DUMMY-CRB' => 'Kab. Cirebon',
            'DUMMY-TSM' => 'Kab. Tasikmalaya',
            'DUMMY-SKB' => 'Kab. Cianjur',
        ];
        $legacyPaIds = [];

        foreach ($legacyOffices as $code => $regionName) {
            $office = KantorPerwakilan::create([
                'nama' => 'Dummy '.$code,
                'kode' => $code,
            ]);
            $region = Region::where('kabupaten_kota', $regionName)->firstOrFail();
            $region->update(['kantor_perwakilan_id' => $office->id]);
            $legacyPaIds[] = PaOrder::factory()->create([
                'region_id' => $region->id,
                'kantor_perwakilan_id' => $office->id,
                'batch_id' => null,
            ])->id;
        }

        $this->seed(KantorPerwakilanDemoSeeder::class);
        $this->seed(KantorPerwakilanDemoSeeder::class);

        $this->assertSame(3, KantorPerwakilan::count());
        $this->assertSame(
            ['KP Bandung', 'KP Cirebon', 'KP Tasikmalaya'],
            KantorPerwakilan::orderBy('nama')->pluck('nama')->all()
        );
        $this->assertSame(0, KantorPerwakilan::where('nama', 'like', '%dummy%')->count());
        $this->assertSame(0, KantorPerwakilan::where('kode', 'like', 'DUMMY-%')->count());
        $this->assertSame(7, PaOrder::whereIn('id', $legacyPaIds)->whereNotNull('kantor_perwakilan_id')->count());
        $this->assertSame(
            KantorPerwakilan::where('kode', 'BDG')->value('id'),
            PaOrder::where('id', $legacyPaIds[1])->value('kantor_perwakilan_id')
        );
        $this->assertSame(
            KantorPerwakilan::where('kode', 'TSM')->value('id'),
            PaOrder::where('id', $legacyPaIds[2])->value('kantor_perwakilan_id')
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $this->actingAs($admin)
            ->getJson(route('admin.map.summary'))
            ->assertOk()
            ->assertJsonPath('clusters.0.name', 'KP Bandung')
            ->assertJsonPath('clusters.1.name', 'KP Cirebon')
            ->assertJsonPath('clusters.2.name', 'KP Tasikmalaya')
            ->assertJsonCount(3, 'clusters');
    }

    public function test_region_can_store_office_hierarchy_metadata(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $office = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'office_name' => 'Kantor Perwakilan Tasikmalaya',
            'office_code' => 'KP-TSM',
            'level' => 'office',
            'is_office' => true,
            'parent_group' => 'Tasikmalaya',
        ]);

        $area = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
            'parent_region_id' => $office->id,
            'level' => 'kelurahan',
            'is_office' => false,
        ]);

        $this->assertDatabaseHas('regions', [
            'id' => $office->id,
            'office_code' => 'KP-TSM',
            'is_office' => true,
        ]);

        $this->assertDatabaseHas('regions', [
            'id' => $area->id,
            'parent_region_id' => $office->id,
            'level' => 'kelurahan',
        ]);

        $this->assertTrue($office->fresh()->is_office);
        $this->assertSame('Kantor Perwakilan Tasikmalaya', $office->fresh()->office_name);
    }
}
