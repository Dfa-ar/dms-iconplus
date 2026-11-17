<?php

namespace Tests\Feature;

use App\Models\KantorPerwakilan;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionOfficeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_seeder_includes_all_west_java_cities_and_regencies_idempotently(): void
    {
        $this->seed(\Database\Seeders\RegionSeeder::class);
        $this->seed(\Database\Seeders\RegionSeeder::class);

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

        $this->seed(\Database\Seeders\RegionSeeder::class);

        $this->assertSame('KP Bandung', $region->fresh()->parent_group);
        $this->assertSame($office->id, $region->fresh()->kantor_perwakilan_id);
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
