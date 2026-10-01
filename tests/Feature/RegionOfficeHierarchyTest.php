<?php

namespace Tests\Feature;

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
